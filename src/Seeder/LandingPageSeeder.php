<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Seeder;

use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\DeterministicValueGenerator;
use Kommandhub\DemoData\Service\SeedReport;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * A landing page of its own for each sales channel.
 *
 * Every channel sharing one layout makes five different shops look like one
 * shop with five URLs, which defeats the point of demonstrating multi-channel.
 * Each gets its own CMS page: a hero, its own copy, a slider of its own
 * products and a feature panel.
 *
 * It runs after the product pass because the slider references real product
 * ids, and a slider is the one block on the page that cannot be written before
 * the products it points at exist.
 *
 * Whether the page is actually applied depends on who owns the root. A root
 * this plugin created is ours to lay out. A root that came with an adopted
 * sales channel belongs to the merchant, so the page is still built — it is
 * there to switch to — but the root is only pointed at it if it has no layout
 * at all.
 */
class LandingPageSeeder
{
    private const SLIDER_PRODUCTS = 10;

    /**
     * @param EntityRepository<CmsPageCollection> $cmsPageRepository
     * @param EntityRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(
        private readonly EntityRepository $cmsPageRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly DemoIdGenerator $ids,
        private readonly DeterministicValueGenerator $values
    ) {
    }

    /**
     * @param array<string, mixed> $definition blueprint sales-channel definition
     * @param array<string, string> $mediaIds relative media path => media id
     * @param array<int, array{leaf: array<string, mixed>, productIds: array<int, string>}> $written
     */
    public function seed(
        Context $context,
        SeedReport $report,
        string $channelKey,
        array $definition,
        string $rootCategoryId,
        bool $rootOwned,
        array $mediaIds,
        array $written
    ): void {
        /** @var array<string, string> $copy */
        $copy = \is_array($definition['landing'] ?? null) ? $definition['landing'] : [];

        if ($copy === []) {
            return;
        }

        $pageId = $this->ids->id('cms-page', $channelKey);

        if ($this->cmsPageRepository->searchIds(new Criteria([$pageId]), $context)->firstId() === null) {
            $this->cmsPageRepository->create(
                [$this->page($pageId, $channelKey, $definition, $copy, $mediaIds, $written)],
                $context
            );
            $report->created('landing_page');
        } else {
            // Layouts get edited by hand in the admin. Rebuilding one on every
            // run would quietly throw that work away.
            $report->reused('landing_page');
        }

        $this->assign($context, $report, $rootCategoryId, $rootOwned, $pageId, $copy);
    }

    /**
     * @param array<string, string> $copy
     */
    private function assign(
        Context $context,
        SeedReport $report,
        string $rootCategoryId,
        bool $rootOwned,
        string $pageId,
        array $copy
    ): void {
        if (!$rootOwned) {
            // Asked of the database, not the entity: CategorySubscriber fills a
            // blank cmsPageId with the system default as it loads, so the getter
            // never reports an empty layout.
            $criteria = new Criteria([$rootCategoryId]);
            $criteria->addFilter(new EqualsFilter('cmsPageId', null));

            if ($this->categoryRepository->searchIds($criteria, $context)->firstId() === null) {
                $report->skipped('landing_page_assignment');
                $report->note(\sprintf(
                    'The adopted sales channel\'s root category already has a layout, so "%s" was built but not applied. '
                    . 'Assign it by hand in the admin if you want it.',
                    $copy['headline'] ?? 'the generated landing page'
                ));

                return;
            }
        }

        $criteria = new Criteria([$rootCategoryId]);
        $criteria->addFilter(new EqualsFilter('cmsPageId', $pageId));

        if ($this->categoryRepository->searchIds($criteria, $context)->firstId() !== null) {
            $report->reused('landing_page_assignment');

            return;
        }

        $this->categoryRepository->update([['id' => $rootCategoryId, 'cmsPageId' => $pageId]], $context);
        $report->enriched('landing_page_assignment');
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, string> $copy
     * @param array<string, string> $mediaIds
     * @param array<int, array{leaf: array<string, mixed>, productIds: array<int, string>}> $written
     *
     * @return array<string, mixed>
     */
    private function page(
        string $pageId,
        string $channelKey,
        array $definition,
        array $copy,
        array $mediaIds,
        array $written
    ): array {
        $images = $this->channelImages($definition, $mediaIds);
        $sections = [];

        if (isset($images[0])) {
            $sections[] = $this->section($channelKey, 'hero', 0, 'full_width', [
                $this->block($channelKey, 'hero', 'image-cover', 0, [
                    $this->slot($channelKey, 'hero-image', 'image', 'image', $this->imageConfig($images[0], '420px', 'cover')),
                ]),
            ]);
        }

        $sections[] = $this->section($channelKey, 'intro', \count($sections), 'boxed', [
            $this->block($channelKey, 'intro', 'text-hero', 0, [
                $this->slot($channelKey, 'intro-text', 'text', 'content', $this->textConfig(\sprintf(
                    '<h2>%s</h2><p>%s</p>',
                    htmlspecialchars($copy['headline'] ?? '', \ENT_QUOTES),
                    htmlspecialchars($copy['intro'] ?? '', \ENT_QUOTES)
                ))),
            ]),
        ]);

        $sliderProducts = $this->sliderProducts($channelKey, $written);

        if ($sliderProducts !== []) {
            $sections[] = $this->section($channelKey, 'slider', \count($sections), 'boxed', [
                $this->block($channelKey, 'slider', 'product-slider', 0, [
                    $this->slot(
                        $channelKey,
                        'slider-products',
                        'product-slider',
                        'productSlider',
                        $this->sliderConfig($sliderProducts, $copy['sliderTitle'] ?? 'Featured')
                    ),
                ]),
            ]);
        }

        if (isset($images[1])) {
            $sections[] = $this->section($channelKey, 'feature', \count($sections), 'boxed', [
                $this->block($channelKey, 'feature', 'image-text', 0, [
                    $this->slot($channelKey, 'feature-image', 'image', 'left', $this->imageConfig($images[1], '320px', 'standard')),
                    $this->slot($channelKey, 'feature-text', 'text', 'right', $this->textConfig($copy['featureText'] ?? '')),
                ]),
            ]);
        }

        return [
            'id' => $pageId,
            'type' => 'landingpage',
            'name' => \sprintf(
                '%s — Home',
                \is_string($definition['name'] ?? null) ? $definition['name'] : $channelKey
            ),
            // Never locked: a locked layout cannot be edited in the admin, and
            // the whole point of demo data is that people poke at it.
            'locked' => false,
            'sections' => $sections,
        ];
    }

    /**
     * The channel's own category imagery, so no two channels open on the same
     * photograph.
     *
     * @param array<string, mixed> $definition
     * @param array<string, string> $mediaIds
     *
     * @return array<int, string>
     */
    private function channelImages(array $definition, array $mediaIds): array
    {
        $images = [];

        /** @var array<int, array<string, mixed>> $tree */
        $tree = \is_array($definition['tree'] ?? null) ? $definition['tree'] : [];

        foreach ($tree as $node) {
            if (isset($node['image']) && \is_string($node['image']) && isset($mediaIds[$node['image']])) {
                $images[] = $mediaIds[$node['image']];
            }
        }

        return $images;
    }

    /**
     * A spread across the channel's categories rather than the first ten
     * products of the first one, so the slider looks like a shop front.
     *
     * @param array<int, array{leaf: array<string, mixed>, productIds: array<int, string>}> $written
     *
     * @return array<int, string>
     */
    private function sliderProducts(string $channelKey, array $written): array
    {
        $candidates = [];

        foreach ($written as $group) {
            $leafId = $group['leaf']['id'] ?? '';

            $picked = $this->values->pickMany(
                $channelKey . '|slider|' . (\is_string($leafId) ? $leafId : ''),
                $group['productIds'],
                2
            );

            $candidates = [...$candidates, ...$picked];
        }

        return $this->values->pickMany($channelKey . '|slider', $candidates, self::SLIDER_PRODUCTS);
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     *
     * @return array<string, mixed>
     */
    private function section(string $channelKey, string $key, int $position, string $sizingMode, array $blocks): array
    {
        return [
            'id' => $this->ids->id('cms-section', $channelKey, $key),
            'position' => $position,
            'type' => 'default',
            'sizingMode' => $sizingMode,
            'blocks' => $blocks,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $slots
     *
     * @return array<string, mixed>
     */
    private function block(string $channelKey, string $key, string $type, int $position, array $slots): array
    {
        return [
            'id' => $this->ids->id('cms-block', $channelKey, $key),
            'position' => $position,
            'type' => $type,
            'sectionPosition' => 'main',
            'marginTop' => '20px',
            'marginBottom' => '20px',
            'slots' => $slots,
        ];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function slot(string $channelKey, string $key, string $type, string $slot, array $config): array
    {
        return [
            'id' => $this->ids->id('cms-slot', $channelKey, $key),
            'type' => $type,
            'slot' => $slot,
            'config' => $config,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function imageConfig(string $mediaId, string $minHeight, string $displayMode): array
    {
        return [
            'url' => ['source' => 'static', 'value' => null],
            'media' => ['source' => 'static', 'value' => $mediaId],
            'newTab' => ['source' => 'static', 'value' => false],
            'minHeight' => ['source' => 'static', 'value' => $minHeight],
            'displayMode' => ['source' => 'static', 'value' => $displayMode],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function textConfig(string $html): array
    {
        return [
            'content' => ['source' => 'static', 'value' => $html],
            'verticalAlign' => ['source' => 'static', 'value' => null],
        ];
    }

    /**
     * @param array<int, string> $productIds
     *
     * @return array<string, mixed>
     */
    private function sliderConfig(array $productIds, string $title): array
    {
        return [
            'products' => ['source' => 'static', 'value' => array_values($productIds)],
            'title' => ['source' => 'static', 'value' => $title],
            'boxLayout' => ['source' => 'static', 'value' => 'standard'],
            'displayMode' => ['source' => 'static', 'value' => 'standard'],
            'navigation' => ['source' => 'static', 'value' => true],
            'rotate' => ['source' => 'static', 'value' => false],
            'border' => ['source' => 'static', 'value' => false],
            'elMinWidth' => ['source' => 'static', 'value' => '300px'],
            'verticalAlign' => ['source' => 'static', 'value' => null],
        ];
    }
}
