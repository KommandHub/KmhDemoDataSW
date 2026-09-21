<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Seeder;

use Kommandhub\DemoData\Service\EntityResolver;
use Kommandhub\DemoData\Service\SeedReport;
use Kommandhub\DemoData\Util\DemoDataConstants;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

/**
 * The one place a category row is created or topped up.
 *
 * Three callers need the same thing — sales-channel roots, the catalogue tree,
 * the footer trees — and the interesting part is identical every time: resolve
 * by deterministic id, fall back to (name, parentId), create or enrich, stamp
 * the provenance custom fields. Only the payload differs, so only the payload is
 * the caller's business.
 *
 * (name, parentId) is the natural key rather than name alone: two sales channels
 * may legitimately both want a "Footwear", and they must end up with two rows.
 */
class CategoryWriter
{
    /**
     * @param EntityRepository<CategoryCollection> $categoryRepository
     * @param EntityRepository<CmsPageCollection> $cmsPageRepository
     */
    public function __construct(
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $cmsPageRepository,
        private readonly EntityResolver $resolver
    ) {
    }

    /**
     * @param array<int, string> $path key path behind the deterministic id
     * @param array<string, mixed> $fields payload beyond id / parentId / name
     * @param array<int, string> $fillIfEmpty fields to top up on an existing row
     */
    public function upsert(
        Context $context,
        SeedReport $report,
        string $reportKey,
        array $path,
        ?string $parentId,
        string $name,
        array $fields,
        array $fillIfEmpty = []
    ): string {
        $resolved = $this->resolver->resolve(
            $this->categoryRepository,
            $context,
            $this->resolver->ids()->id('category', ...$path),
            [new EqualsFilter('name', $name), new EqualsFilter('parentId', $parentId)]
        );

        $payload = [
            'id' => $resolved->id,
            'parentId' => $parentId,
            'name' => $name,
            'customFields' => [
                DemoDataConstants::FIELD_SOURCE_KEY => $this->resolver->ids()->key('category', ...$path),
                DemoDataConstants::FIELD_GENERATED => true,
            ],
            ...$fields,
        ];

        if (!$resolved->exists) {
            $this->categoryRepository->create([$payload], $context);
            $report->created($reportKey);

            return $resolved->id;
        }

        $resolved->adopted ? $report->adopted($reportKey) : $report->reused($reportKey);

        $enrichment = $this->resolver->enrichmentPayload($resolved, $payload, [...$fillIfEmpty, 'customFields']);

        if ($enrichment !== null) {
            $this->categoryRepository->update([$enrichment], $context);
            $report->enriched($reportKey);
        }

        return $resolved->id;
    }

    /**
     * Gives a category a layout only if it has none.
     *
     * Used for roots that were resolved rather than created — an adopted sales
     * channel's navigation root never passes through `upsert`, so this is the
     * only place its missing layout can be filled. A root that already has a
     * layout is the merchant's design and is left alone.
     *
     * "Has none" has to be asked of the database, not of a loaded entity:
     * Shopware's CategorySubscriber fills a blank `cmsPageId` with the system
     * default as the entity loads, so `getCmsPageId()` never returns null and
     * every root would look like it already had a layout. That same default is
     * a product listing, which is exactly what a navigation root must not be.
     */
    public function fillLayoutIfEmpty(
        Context $context,
        SeedReport $report,
        string $reportKey,
        string $categoryId,
        string $cmsPageType
    ): void {
        $criteria = new Criteria([$categoryId]);
        $criteria->addFilter(new EqualsFilter('cmsPageId', null));

        if ($this->categoryRepository->searchIds($criteria, $context)->firstId() === null) {
            return;
        }

        $cmsPageId = $this->defaultCmsPageId($context, $cmsPageType);

        if ($cmsPageId === null) {
            return;
        }

        $this->categoryRepository->update([['id' => $categoryId, 'cmsPageId' => $cmsPageId]], $context);
        $report->enriched($reportKey);
    }

    /**
     * Points a category at a layout, but only when what it has now is one of
     * the layouts this plugin is allowed to replace.
     *
     * Used for footer pages, which this plugin creates and then hands a
     * generic stock layout before it has a written page to give them. Passing
     * that stock layout as replaceable is what lets the real page take over,
     * while a layout the merchant chose afterwards is left alone.
     *
     * @param array<int, string|null> $replaceable layouts we may overwrite; null means "no layout at all"
     */
    public function assignLayout(
        Context $context,
        SeedReport $report,
        string $reportKey,
        string $categoryId,
        string $cmsPageId,
        array $replaceable
    ): void {
        $current = $this->currentLayoutId($context, $categoryId);

        if ($current === $cmsPageId) {
            $report->reused($reportKey);

            return;
        }

        if (!\in_array($current, $replaceable, true)) {
            $report->skipped($reportKey);

            return;
        }

        $this->categoryRepository->update([['id' => $categoryId, 'cmsPageId' => $cmsPageId]], $context);
        $report->enriched($reportKey);
    }

    /**
     * The layout actually stored against a category.
     *
     * Read through searchIds rather than the entity, because CategorySubscriber
     * substitutes the system default into a blank cmsPageId as the entity
     * loads — the getter never reports an empty layout.
     */
    private function currentLayoutId(Context $context, string $categoryId): ?string
    {
        $empty = new Criteria([$categoryId]);
        $empty->addFilter(new EqualsFilter('cmsPageId', null));

        if ($this->categoryRepository->searchIds($empty, $context)->firstId() !== null) {
            return null;
        }

        $category = $this->categoryRepository->search(new Criteria([$categoryId]), $context)->getEntities()->first();

        return $category?->getCmsPageId();
    }

    /**
     * A locked CMS layout of the given type — Shopware's own defaults. Without
     * one, a seeded category renders an empty page.
     */
    public function defaultCmsPageId(Context $context, string $type): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('type', $type));
        $criteria->addFilter(new EqualsFilter('locked', true));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->setLimit(1);

        $id = $this->cmsPageRepository->searchIds($criteria, $context)->firstId();

        if ($id !== null) {
            return $id;
        }

        $fallback = new Criteria();
        $fallback->addFilter(new EqualsFilter('type', $type));
        $fallback->setLimit(1);

        return $this->cmsPageRepository->searchIds($fallback, $context)->firstId();
    }
}
