<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Seeder {
    final class MediaSeederTestRuntime
    {
        public static string $tempDir = '';

        /** @var array<string, string|false> */
        public static array $downloads = [];
    }

    function sys_get_temp_dir(): string
    {
        return MediaSeederTestRuntime::$tempDir !== '' ? MediaSeederTestRuntime::$tempDir : \sys_get_temp_dir();
    }

    function tempnam(string $directory, string $prefix): string|false
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        return $directory . '/' . $prefix . uniqid('', true);
    }

    function file_get_contents(string $filename, bool $useIncludePath = false, mixed $context = null): string|false
    {
        return MediaSeederTestRuntime::$downloads[$filename] ?? false;
    }
}

namespace Kommandhub\DemoData\Tests\Unit\Seeder {
    use Kommandhub\DemoData\Seeder\MediaSeeder;
    use Kommandhub\DemoData\Seeder\MediaSeederTestRuntime;
    use Kommandhub\DemoData\Service\DemoIdGenerator;
    use Kommandhub\DemoData\Service\SeedReport;
    use PHPUnit\Framework\MockObject\MockObject;
    use PHPUnit\Framework\TestCase;
    use Psr\Log\LoggerInterface;
    use Shopware\Core\Content\Media\File\FileSaver;
    use Shopware\Core\Content\Media\MediaService;
    use Shopware\Core\Content\Media\MediaCollection;
    use Shopware\Core\Content\Media\MediaEntity;
    use Shopware\Core\Framework\Context;
    use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
    use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
    use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

    class MediaSeederTest extends TestCase
    {
        private string $runtimeDir;

        protected function setUp(): void
        {
            $this->runtimeDir = dirname(__DIR__, 3) . '/build/test-runtime/media';

            if (!is_dir($this->runtimeDir)) {
                mkdir($this->runtimeDir, 0777, true);
            }

            MediaSeederTestRuntime::$tempDir = $this->runtimeDir;
            MediaSeederTestRuntime::$downloads = [];
        }

        protected function tearDown(): void
        {
            foreach (glob($this->runtimeDir . '/*') ?: [] as $path) {
                @unlink($path);
            }
        }

        public function testSeedReusesExistingFolderAndMedia(): void
        {
            $mediaRepository = $this->createMock(EntityRepository::class);
            $mediaFolderRepository = $this->createMock(EntityRepository::class);
            $fileSaver = $this->createMock(FileSaver::class);
            $mediaService = $this->createMock(MediaService::class);
            $logger = $this->createMock(LoggerInterface::class);
            $ids = new DemoIdGenerator();

            $seeder = $this->getMockBuilder(MediaSeeder::class)
                ->setConstructorArgs([$mediaRepository, $mediaFolderRepository, $fileSaver, $mediaService, $ids, $logger])
                ->onlyMethods(['mediaRoot'])
                ->getMock();

            $filePath = $this->runtimeDir . '/test.jpg';
            file_put_contents($filePath, 'fake-content');
            $seeder->method('mediaRoot')->willReturn($this->runtimeDir);

            $context = Context::createDefaultContext();
            $report = new SeedReport();

            $folderIds = $this->createMock(IdSearchResult::class);
            $folderIds->method('firstId')->willReturn('folder-id');
            $mediaFolderRepository->method('searchIds')->willReturn($folderIds);

            $media = new MediaEntity();
            $media->setId($ids->id('media', 'test.jpg'));
            $media->setFileName('test');
            $mediaRepository->method('search')->willReturn($this->entitySearchResult(new MediaCollection([$media])));
            $fileSaver->expects($this->never())->method('persistFileToMedia');

            $result = $seeder->seed($context, $report, ['test.jpg']);

            $this->assertSame(['test.jpg' => $ids->id('media', 'test.jpg')], $result);
        }

        public function testSeedSkipsMissingLocalFile(): void
        {
            $mediaRepository = $this->createMock(EntityRepository::class);
            $mediaFolderRepository = $this->createMock(EntityRepository::class);
            $fileSaver = $this->createMock(FileSaver::class);
            $mediaService = $this->createMock(MediaService::class);
            $logger = $this->createMock(LoggerInterface::class);
            $ids = new DemoIdGenerator();

            $seeder = $this->getMockBuilder(MediaSeeder::class)
                ->setConstructorArgs([$mediaRepository, $mediaFolderRepository, $fileSaver, $mediaService, $ids, $logger])
                ->onlyMethods(['mediaRoot'])
                ->getMock();
            $seeder->method('mediaRoot')->willReturn($this->runtimeDir);

            $folderIds = $this->createMock(IdSearchResult::class);
            $folderIds->method('firstId')->willReturn('folder-id');
            $mediaFolderRepository->method('searchIds')->willReturn($folderIds);

            $logger->expects($this->once())->method('warning')->with('Demo media file missing', $this->arrayHasKey('path'));

            $result = $seeder->seed(Context::createDefaultContext(), new SeedReport(), ['missing.jpg']);

            $this->assertSame([], $result);
        }

        public function testSeedCreatesMediaWhenBinaryExistsButNoRowDoes(): void
        {
            $mediaRepository = $this->createMock(EntityRepository::class);
            $mediaFolderRepository = $this->createMock(EntityRepository::class);
            $fileSaver = $this->createMock(FileSaver::class);
            $mediaService = $this->createMock(MediaService::class);
            $logger = $this->createMock(LoggerInterface::class);
            $ids = new DemoIdGenerator();

            $seeder = $this->getMockBuilder(MediaSeeder::class)
                ->setConstructorArgs([$mediaRepository, $mediaFolderRepository, $fileSaver, $mediaService, $ids, $logger])
                ->onlyMethods(['mediaRoot'])
                ->getMock();

            $filePath = $this->runtimeDir . '/cover.jpg';
            file_put_contents($filePath, str_repeat('x', 1500));
            $seeder->method('mediaRoot')->willReturn($this->runtimeDir);

            $folderIds = $this->createMock(IdSearchResult::class);
            $folderIds->method('firstId')->willReturn('folder-id');
            $mediaFolderRepository->method('searchIds')->willReturn($folderIds);
            $mediaRepository->method('search')->willReturn($this->entitySearchResult(new MediaCollection([])));
            $mediaRepository->expects($this->once())->method('create');
            $fileSaver->expects($this->once())->method('persistFileToMedia');

            $result = $seeder->seed(Context::createDefaultContext(), new SeedReport(), ['cover.jpg']);

            $this->assertArrayHasKey('cover.jpg', $result);
        }

        public function testSeedSkipsWhenPersistingLocalMediaFails(): void
        {
            $mediaRepository = $this->createMock(EntityRepository::class);
            $mediaFolderRepository = $this->createMock(EntityRepository::class);
            $fileSaver = $this->createMock(FileSaver::class);
            $mediaService = $this->createMock(MediaService::class);
            $logger = $this->createMock(LoggerInterface::class);
            $ids = new DemoIdGenerator();

            $seeder = $this->getMockBuilder(MediaSeeder::class)
                ->setConstructorArgs([$mediaRepository, $mediaFolderRepository, $fileSaver, $mediaService, $ids, $logger])
                ->onlyMethods(['mediaRoot'])
                ->getMock();

            $filePath = $this->runtimeDir . '/broken.jpg';
            file_put_contents($filePath, str_repeat('x', 1500));
            $seeder->method('mediaRoot')->willReturn($this->runtimeDir);

            $folderIds = $this->createMock(IdSearchResult::class);
            $folderIds->method('firstId')->willReturn('folder-id');
            $mediaFolderRepository->method('searchIds')->willReturn($folderIds);
            $mediaRepository->method('search')->willReturn($this->entitySearchResult(new MediaCollection([])));
            $fileSaver->method('persistFileToMedia')->willThrowException(new \RuntimeException('disk full'));
            $logger->expects($this->once())->method('warning')->with('Could not persist demo media file', $this->arrayHasKey('path'));

            $result = $seeder->seed(Context::createDefaultContext(), new SeedReport(), ['broken.jpg']);

            $this->assertSame([], $result);
        }

        public function testSeedRemoteReusesAlreadyImportedPhotosWithoutDownloading(): void
        {
            $mediaRepository = $this->createMock(EntityRepository::class);
            $mediaFolderRepository = $this->createMock(EntityRepository::class);
            $fileSaver = $this->createMock(FileSaver::class);
            $mediaService = $this->createMock(MediaService::class);
            $logger = $this->createMock(LoggerInterface::class);
            $ids = new DemoIdGenerator();

            $seeder = new MediaSeeder($mediaRepository, $mediaFolderRepository, $fileSaver, $mediaService, $ids, $logger);

            $folderIds = $this->createMock(IdSearchResult::class);
            $folderIds->method('firstId')->willReturn('folder-id');
            $mediaFolderRepository->method('searchIds')->willReturn($folderIds);

            // Already imported, so the network is never touched and no row is written.
            $imported = $this->createMock(IdSearchResult::class);
            $imported->method('getIds')->willReturn([$ids->id('media', 'photo1')]);
            $mediaRepository->method('searchIds')->willReturn($imported);
            $mediaRepository->expects($this->never())->method('upsert');
            $mediaService->expects($this->never())->method('saveFile');

            $result = $seeder->seedRemote(Context::createDefaultContext(), new SeedReport(), ['photo1']);

            $this->assertSame(['photo1' => $ids->id('media', 'photo1')], $result);
        }

        public function testSeedRemoteImportsADownloadedPhotoThroughMediaService(): void
        {
            $mediaRepository = $this->createMock(EntityRepository::class);
            $mediaFolderRepository = $this->createMock(EntityRepository::class);
            $fileSaver = $this->createMock(FileSaver::class);
            $mediaService = $this->createMock(MediaService::class);
            $logger = $this->createMock(LoggerInterface::class);
            $ids = new DemoIdGenerator();

            $seeder = new MediaSeeder($mediaRepository, $mediaFolderRepository, $fileSaver, $mediaService, $ids, $logger);

            $folderIds = $this->createMock(IdSearchResult::class);
            $folderIds->method('firstId')->willReturn('folder-id');
            $mediaFolderRepository->method('searchIds')->willReturn($folderIds);

            $imported = $this->createMock(IdSearchResult::class);
            $imported->method('getIds')->willReturn([]);
            $mediaRepository->method('searchIds')->willReturn($imported);

            // The row is written first so the file has something to attach to.
            $mediaRepository->expects($this->once())->method('upsert');

            MediaSeederTestRuntime::$downloads = [$this->photoUrl('photo1') => $this->jpeg()];

            $mediaService->expects($this->once())
                ->method('saveFile')
                ->with($this->jpeg(), 'jpg', 'image/jpeg', 'photo1', $this->anything(), null, $ids->id('media', 'photo1'), false);

            $report = new SeedReport();
            $result = $seeder->seedRemote(Context::createDefaultContext(), $report, ['photo1']);

            $this->assertSame(['photo1' => $ids->id('media', 'photo1')], $result);
            $this->assertSame(1, $report->totalCreated());
        }

        public function testSeedRemoteSkipsAPhotoThatCannotBeFetched(): void
        {
            $mediaRepository = $this->createMock(EntityRepository::class);
            $mediaFolderRepository = $this->createMock(EntityRepository::class);
            $fileSaver = $this->createMock(FileSaver::class);
            $mediaService = $this->createMock(MediaService::class);
            $logger = $this->createMock(LoggerInterface::class);

            $seeder = new MediaSeeder($mediaRepository, $mediaFolderRepository, $fileSaver, $mediaService, new DemoIdGenerator(), $logger);

            $folderIds = $this->createMock(IdSearchResult::class);
            $folderIds->method('firstId')->willReturn('folder-id');
            $mediaFolderRepository->method('searchIds')->willReturn($folderIds);

            $imported = $this->createMock(IdSearchResult::class);
            $imported->method('getIds')->willReturn([]);
            $mediaRepository->method('searchIds')->willReturn($imported);

            // No stubbed response, so the fetch fails.
            MediaSeederTestRuntime::$downloads = [];

            $mediaService->expects($this->never())->method('saveFile');
            $logger->expects($this->once())->method('warning')->with('Demo photo could not be fetched', $this->anything());

            // A shop that cannot reach the network still seeds; it just has no photographs.
            $this->assertSame([], $seeder->seedRemote(Context::createDefaultContext(), new SeedReport(), ['photo1']));
        }

        public function testSeedRemoteSurvivesAFailedImport(): void
        {
            $mediaRepository = $this->createMock(EntityRepository::class);
            $mediaFolderRepository = $this->createMock(EntityRepository::class);
            $fileSaver = $this->createMock(FileSaver::class);
            $mediaService = $this->createMock(MediaService::class);
            $logger = $this->createMock(LoggerInterface::class);

            $seeder = new MediaSeeder($mediaRepository, $mediaFolderRepository, $fileSaver, $mediaService, new DemoIdGenerator(), $logger);

            $folderIds = $this->createMock(IdSearchResult::class);
            $folderIds->method('firstId')->willReturn('folder-id');
            $mediaFolderRepository->method('searchIds')->willReturn($folderIds);

            $imported = $this->createMock(IdSearchResult::class);
            $imported->method('getIds')->willReturn([]);
            $mediaRepository->method('searchIds')->willReturn($imported);

            MediaSeederTestRuntime::$downloads = [$this->photoUrl('photo1') => $this->jpeg()];
            $mediaService->method('saveFile')->willThrowException(new \RuntimeException('disk full'));

            $logger->expects($this->once())->method('warning')->with('Could not import a fetched demo photo', $this->anything());

            $report = new SeedReport();
            $this->assertSame([], $seeder->seedRemote(Context::createDefaultContext(), $report, ['photo1']));
            $this->assertSame(0, $report->totalCreated());
        }

        /**
         * A truncated or error response written to the media library is a broken
         * image, which is harder to notice than a missing one.
         */
        public function testDownloadRejectsAnythingThatIsNotAWholeJpeg(): void
        {
            $seeder = $this->downloadProbe();

            MediaSeederTestRuntime::$downloads = [];
            $this->assertNull($seeder->callDownload('missing'));

            MediaSeederTestRuntime::$downloads = [$this->photoUrl('tiny') => 'too-short'];
            $this->assertNull($seeder->callDownload('tiny'));

            MediaSeederTestRuntime::$downloads = [$this->photoUrl('nonjpeg') => str_repeat('A', 2048)];
            $this->assertNull($seeder->callDownload('nonjpeg'));
        }

        public function testDownloadReturnsTheBytesRatherThanAPath(): void
        {
            $seeder = $this->downloadProbe();
            $jpeg = $this->jpeg();

            MediaSeederTestRuntime::$downloads = [$this->photoUrl('good') => $jpeg];

            // Nothing of ours touches the local disk any more.
            $this->assertSame($jpeg, $seeder->callDownload('good'));
        }

        public function testMediaRootPointsAtBundledDirectory(): void
        {
            $seeder = new MediaSeeder(
                $this->createMock(EntityRepository::class),
                $this->createMock(EntityRepository::class),
                $this->createMock(FileSaver::class),
                $this->createMock(MediaService::class),
                new DemoIdGenerator(),
                $this->createMock(LoggerInterface::class)
            );

            $this->assertStringEndsWith('/Resources/demo-media', $seeder->mediaRoot());
            $this->assertDirectoryExists($seeder->mediaRoot());
        }

        private function downloadProbe(): MediaSeeder
        {
            return new class($this->createMock(EntityRepository::class), $this->createMock(EntityRepository::class), $this->createMock(FileSaver::class), $this->createMock(MediaService::class), new DemoIdGenerator(), $this->createMock(LoggerInterface::class)) extends MediaSeeder {
                public function callDownload(string $photoId): ?string
                {
                    return $this->download($photoId);
                }
            };
        }

        private function photoUrl(string $photoId): string
        {
            return \sprintf(
                'https://images.unsplash.com/%s?w=900&h=900&fit=crop&crop=entropy&q=72&fm=jpg',
                $photoId
            );
        }

        private function jpeg(): string
        {
            return "\xFF\xD8" . str_repeat('x', 2048);
        }

        public function testResolveFolderIdFallsBackToOwnFolderAndCreatesItWhenNeeded(): void
        {
            $mediaRepository = $this->createMock(EntityRepository::class);
            $mediaFolderRepository = $this->createMock(EntityRepository::class);
            $fileSaver = $this->createMock(FileSaver::class);
            $mediaService = $this->createMock(MediaService::class);
            $logger = $this->createMock(LoggerInterface::class);
            $ids = new DemoIdGenerator();
            $seeder = new MediaSeeder($mediaRepository, $mediaFolderRepository, $fileSaver, $mediaService, $ids, $logger);

            $none = $this->createMock(IdSearchResult::class);
            $none->method('firstId')->willReturn(null);
            $own = $this->createMock(IdSearchResult::class);
            $own->method('firstId')->willReturn($ids->id('media-folder', 'Kommandhub Demo Data'));

            $mediaFolderRepository->method('searchIds')->willReturnOnConsecutiveCalls($none, $own);

            $method = (new \ReflectionClass($seeder))->getMethod('resolveFolderId');
            $result = $method->invoke($seeder, Context::createDefaultContext(), new SeedReport());
            $this->assertSame($ids->id('media-folder', 'Kommandhub Demo Data'), $result);

            $mediaFolderRepository2 = $this->createMock(EntityRepository::class);
            $seeder2 = new MediaSeeder($mediaRepository, $mediaFolderRepository2, $fileSaver, $mediaService, $ids, $logger);
            $mediaFolderRepository2->method('searchIds')->willReturn($none);
            $mediaFolderRepository2->expects($this->once())->method('create');
            $result2 = $method->invoke($seeder2, Context::createDefaultContext(), new SeedReport());
            $this->assertSame($ids->id('media-folder', 'Kommandhub Demo Data'), $result2);
        }

        private function entitySearchResult(MediaCollection $collection): EntitySearchResult&MockObject
        {
            $result = $this->createMock(EntitySearchResult::class);
            $result->method('getEntities')->willReturn($collection);

            return $result;
        }
    }
}
