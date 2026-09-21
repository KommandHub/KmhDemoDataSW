<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Seeder;

use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\SeedReport;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;

/**
 * Imports the bundled demo images into Shopware's media library.
 *
 * Two-step, because that is how Shopware wants it: create the media row, then
 * hand the binary to the FileSaver, which does the thumbnail generation and the
 * filesystem write. A row whose file already landed is skipped outright — the
 * expensive half of this seeder runs exactly once per image, ever.
 *
 * Media failures are reported and swallowed rather than aborting the run: a shop
 * with a misconfigured filesystem should still end up with a catalogue, just
 * without cover images.
 */
class MediaSeeder
{
    private const FOLDER_NAME = 'Kommandhub Demo Data';

    /**
     * Where a manifest photo id is fetched from.
     *
     * The plugin ships photo *ids*, not photographs. Four hundred product
     * images at a usable resolution is tens of megabytes, which does not belong
     * in a plugin repository — and a themed pool small enough to bundle repeats
     * more visibly on a listing page than the unthemed one it replaced.
     *
     * The licence permits both: Unsplash grants the right to download, copy,
     * modify and distribute, commercially, with the sole carve-out being
     * compiling photos to replicate a competing image service. Fetching is the
     * engineering choice, not the legal one.
     */
    private const PHOTO_URL = 'https://images.unsplash.com/%s?w=900&h=900&fit=crop&crop=entropy&q=72&fm=jpg';

    private const FETCH_TIMEOUT_SECONDS = 20;

    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     * @param EntityRepository<MediaFolderCollection> $mediaFolderRepository
     */
    public function __construct(
        private readonly EntityRepository $mediaRepository,
        private readonly EntityRepository $mediaFolderRepository,
        private readonly FileSaver $fileSaver,
        private readonly MediaService $mediaService,
        private readonly DemoIdGenerator $ids,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<int, string> $relativePaths paths under Resources/demo-media
     *
     * @return array<string, string> relative path => media id
     */
    public function seed(Context $context, SeedReport $report, array $relativePaths): array
    {
        $folderId = $this->resolveFolderId($context, $report);
        $media = [];

        foreach ($relativePaths as $relativePath) {
            $mediaId = $this->import($context, $report, $relativePath, $folderId);

            if ($mediaId !== null) {
                $media[$relativePath] = $mediaId;
            }
        }

        return $media;
    }

    /**
     * Imports the manifest's photos, downloading each one once.
     *
     * A photo already in the media library is never fetched again, so only the
     * first seed of an installation needs the network. Anything that cannot be
     * fetched is skipped and reported rather than failing the run — a shop
     * behind a proxy should still get a catalogue, just one that falls back to
     * the bundled imagery.
     *
     * @param array<int, string> $photoIds
     *
     * @return array<string, string> photo id => media id
     */
    public function seedRemote(Context $context, SeedReport $report, array $photoIds): array
    {
        $photoIds = array_values(array_unique($photoIds));

        if ($photoIds === []) {
            return [];
        }

        $folderId = $this->resolveFolderId($context, $report);
        $wanted = [];

        foreach ($photoIds as $photoId) {
            $wanted[$this->ids->id('media', $photoId)] = $photoId;
        }

        // Asked once for all four hundred rather than once per photograph: the
        // answer decides whether we go to the network at all, so it is the
        // query most worth not repeating.
        $alreadyImported = $this->importedMediaIds($context, array_keys($wanted));

        $media = [];
        $pending = [];

        foreach ($wanted as $mediaId => $photoId) {
            if (\in_array($mediaId, $alreadyImported, true)) {
                $media[$photoId] = $mediaId;
                $report->reused('media');

                continue;
            }

            $pending[$mediaId] = $photoId;
        }

        return $media + $this->fetchPending($context, $report, $pending, $folderId);
    }

    /**
     * Where the bundled demo imagery lives.
     */
    public function mediaRoot(): string
    {
        return \dirname(__DIR__) . '/Resources/demo-media';
    }

    /**
     * Imports one bundled file. Reading a shipped asset from the plugin's own
     * directory is not a local disk *write*, which is what the platform's
     * filesystem rules are about.
     */
    private function import(Context $context, SeedReport $report, string $relativePath, ?string $folderId): ?string
    {
        $absolute = $this->mediaRoot() . '/' . $relativePath;

        if (!is_file($absolute)) {
            $report->skipped('media');
            $this->logger->warning('Demo media file missing', ['path' => $absolute]);

            return null;
        }

        $mediaId = $this->ids->id('media', $relativePath);
        $fileName = pathinfo($relativePath, \PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($relativePath, \PATHINFO_EXTENSION));

        $existing = $this->findMedia($context, $mediaId);

        if ($existing !== null && $existing->getFileName() !== null) {
            $report->reused('media');

            return $mediaId;
        }

        if ($existing === null) {
            $this->mediaRepository->create([[
                'id' => $mediaId,
                'mediaFolderId' => $folderId,
                'alt' => ucwords(str_replace('-', ' ', $fileName)),
                'title' => ucwords(str_replace('-', ' ', $fileName)),
            ]], $context);
        }

        try {
            $this->fileSaver->persistFileToMedia(
                new MediaFile(
                    $absolute,
                    (string)(mime_content_type($absolute) ?: 'image/jpeg'),
                    $extension,
                    (int)filesize($absolute)
                ),
                $fileName,
                $mediaId,
                $context
            );
            $report->created('media');
        } catch (\Throwable $exception) {
            // A clashing file name or an unwritable filesystem must not take
            // the whole catalogue down with it.
            $report->skipped('media');
            $this->logger->warning('Could not persist demo media file', [
                'path' => $relativePath,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        return $mediaId;
    }

    /**
     * Downloads and imports the photographs not already in the library.
     *
     * @param array<string, string> $pending media id => photo id
     *
     * @return array<string, string> photo id => media id
     */
    private function fetchPending(Context $context, SeedReport $report, array $pending, ?string $folderId): array
    {
        $media = [];
        $rows = [];

        foreach ($pending as $mediaId => $photoId) {
            $rows[] = ['id' => $mediaId, 'mediaFolderId' => $folderId, 'alt' => 'Demo product photograph', 'title' => 'Demo product photograph'];
        }

        if ($rows !== []) {
            $this->mediaRepository->upsert($rows, $context);
        }

        foreach ($pending as $mediaId => $photoId) {
            $blob = $this->download($photoId);

            if ($blob === null) {
                $report->skipped('media');

                continue;
            }

            try {
                // MediaService takes the bytes directly; nothing of ours ever
                // touches the local disk, which is both simpler and what the
                // platform's own filesystem rules ask for.
                $this->mediaService->saveFile($blob, 'jpg', 'image/jpeg', $photoId, $context, null, $mediaId, false);

                $media[$photoId] = $mediaId;
                $report->created('media');
            } catch (\Throwable $exception) {
                $report->skipped('media');
                $this->logger->warning('Could not import a fetched demo photo', [
                    'photo' => $photoId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $media;
    }

    /**
     * @param array<int, string> $mediaIds
     *
     * @return array<int, string> those that already have a file behind them
     */
    private function importedMediaIds(Context $context, array $mediaIds): array
    {
        $criteria = new Criteria($mediaIds);
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('fileName', null)]));
        $criteria->setLimit(\count($mediaIds));

        return $this->mediaRepository->searchIds($criteria, $context)->getIds();
    }

    /**
     * @return string|null the JPEG bytes, or null when the fetch failed
     */
    protected function download(string $photoId): ?string
    {
        $streamContext = stream_context_create([
            'http' => [
                'timeout' => self::FETCH_TIMEOUT_SECONDS,
                'follow_location' => 1,
                'user_agent' => 'KmhDemoDataSW (Shopware demo data seeder)',
            ],
        ]);

        $bytes = @file_get_contents(\sprintf(self::PHOTO_URL, $photoId), false, $streamContext);

        // A truncated or error response would land in the media library as a
        // broken image, which is harder to notice than a missing one.
        if (!\is_string($bytes) || \strlen($bytes) < 1024 || !str_starts_with($bytes, "\xFF\xD8")) {
            $this->logger->warning('Demo photo could not be fetched', ['photo' => $photoId]);

            return null;
        }

        return $bytes;
    }

    private function findMedia(Context $context, string $mediaId): ?MediaEntity
    {
        $criteria = new Criteria([$mediaId]);

        /** @var MediaEntity|null $media */
        $media = $this->mediaRepository->search($criteria, $context)->getEntities()->first();

        return $media;
    }

    /**
     * Prefers Shopware's own product media folder so thumbnails match the rest
     * of the catalogue; falls back to a folder of our own only if that default
     * is absent.
     */
    private function resolveFolderId(Context $context, SeedReport $report): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('media_folder.defaultFolder.entity', 'product'));
        $criteria->setLimit(1);

        $folderId = $this->mediaFolderRepository->searchIds($criteria, $context)->firstId();

        if ($folderId !== null) {
            $report->reused('media_folder');

            return $folderId;
        }

        $ownId = $this->ids->id('media-folder', self::FOLDER_NAME);

        if ($this->mediaFolderRepository->searchIds(new Criteria([$ownId]), $context)->firstId() !== null) {
            $report->reused('media_folder');

            return $ownId;
        }

        $this->mediaFolderRepository->create([[
            'id' => $ownId,
            'name' => self::FOLDER_NAME,
            'useParentConfiguration' => false,
            'configuration' => [],
        ]], $context);
        $report->created('media_folder');

        return $ownId;
    }
}
