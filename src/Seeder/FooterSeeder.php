<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Seeder;

use Kommandhub\DemoData\Blueprint\DemoBlueprint;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\SeedReport;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * The two footer trees every sales channel shares.
 *
 * Shopware models the footer and service menus per sales channel
 * (`footerCategoryId`, `serviceCategoryId`), which invites one copy of the
 * imprint and the returns policy per channel. This builds one of each and points
 * every channel at it, so the legal pages are written once.
 *
 * The service menu is generated rather than declared: its children are one link
 * per browsable sales channel, so a visitor on the trade storefront can find the
 * grocery one. That is also why it runs last — it cannot be built before the
 * channels it lists exist.
 */
class FooterSeeder
{
    /**
     * @param EntityRepository<CmsPageCollection> $cmsPageRepository
     */
    public function __construct(
        private readonly CategoryWriter $categories,
        private readonly EntityRepository $cmsPageRepository,
        private readonly DemoIdGenerator $ids
    ) {
    }

    /**
     * @param array<int, array{id: string, name: string, url: string}> $channels browsable sales channels, in menu order
     *
     * @return array{footerCategoryId: string, serviceCategoryId: string}
     */
    public function seed(Context $context, SeedReport $report, array $channels): array
    {
        $blueprint = DemoBlueprint::footer();

        return [
            'footerCategoryId' => $this->seedNavigation($context, $report, $blueprint),
            'serviceCategoryId' => $this->seedServiceMenu($context, $report, $blueprint['serviceRoot'], $channels),
        ];
    }

    /**
     * @param array{navigationRoot: string, serviceRoot: string, navigation: array<int, array{name: string, children: array<int, array{name: string, body: string}>}>} $blueprint
     */
    private function seedNavigation(Context $context, SeedReport $report, array $blueprint): string
    {
        $rootId = $this->upsertRoot($context, $report, 'footer_category', $blueprint['navigationRoot']);

        // Footer entries are shop pages, not product listings — a "Privacy
        // Policy" rendered with a filter sidebar is nobody's idea of a footer.
        $shopPageId = $this->categories->defaultCmsPageId($context, 'page');
        $previousColumn = null;

        foreach ($blueprint['navigation'] as $column) {
            $columnId = $this->categories->upsert(
                $context,
                $report,
                'footer_category',
                ['footer', 'navigation', $column['name']],
                $rootId,
                $column['name'],
                $this->pageFields($shopPageId, $previousColumn),
                ['cmsPageId', 'afterCategoryId']
            );

            $previousColumn = $columnId;
            $previousChild = null;

            foreach ($column['children'] as $child) {
                $categoryId = $this->categories->upsert(
                    $context,
                    $report,
                    'footer_category',
                    ['footer', 'navigation', $column['name'], $child['name']],
                    $columnId,
                    $child['name'],
                    $this->pageFields($shopPageId, $previousChild),
                    ['cmsPageId', 'afterCategoryId']
                );

                $previousChild = $categoryId;

                // Without a written page of its own, a footer entry inherits the
                // generic shop-page layout, whose text slot is empty — so every
                // link in the footer of every page opens a blank page.
                $this->assignPage($context, $report, $column['name'], $child, $categoryId, $shopPageId);
            }
        }

        return $rootId;
    }

    /**
     * @param array<int, array{id: string, name: string, url: string}> $channels
     */
    private function seedServiceMenu(Context $context, SeedReport $report, string $rootName, array $channels): string
    {
        $rootId = $this->upsertRoot($context, $report, 'service_category', $rootName);
        $previous = null;

        foreach ($channels as $channel) {
            // Keyed on the channel id, not its name: renaming a sales channel
            // should move the existing link, not orphan it and mint a new one.
            $previous = $this->categories->upsert(
                $context,
                $report,
                'service_category',
                ['footer', 'service', $channel['id']],
                $rootId,
                $channel['name'],
                [
                    'active' => true,
                    'visible' => true,
                    'type' => CategoryDefinition::TYPE_LINK,
                    'linkType' => CategoryDefinition::LINK_TYPE_EXTERNAL,
                    'externalLink' => $channel['url'],
                    'linkNewTab' => false,
                    ...($previous === null ? [] : ['afterCategoryId' => $previous]),
                ],
                ['externalLink', 'afterCategoryId']
            );
        }

        if ($channels === []) {
            $report->note('No sales channel has a browsable domain, so the service menu was left empty.');
        }

        return $rootId;
    }

    /**
     * One CMS page per footer entry, carrying that entry's text.
     *
     * Shopware's own shop-page layouts exist but ship German placeholder text,
     * so they are not reused; an existing generated page is left alone, because
     * these are exactly the pages a merchant rewrites first.
     *
     * @param array{name: string, body: string} $child
     */
    private function assignPage(
        Context $context,
        SeedReport $report,
        string $columnName,
        array $child,
        string $categoryId,
        ?string $shopPageId
    ): void {
        $path = ['footer', $columnName, $child['name']];
        $pageId = $this->ids->id('cms-page', ...$path);

        if ($this->cmsPageRepository->searchIds(new Criteria([$pageId]), $context)->firstId() === null) {
            $this->cmsPageRepository->create([[
                'id' => $pageId,
                'type' => 'page',
                'name' => $child['name'],
                'locked' => false,
                'sections' => [[
                    'id' => $this->ids->id('cms-section', ...$path),
                    'position' => 0,
                    'type' => 'default',
                    'sizingMode' => 'boxed',
                    'blocks' => [[
                        'id' => $this->ids->id('cms-block', ...$path),
                        'position' => 0,
                        'type' => 'text',
                        'sectionPosition' => 'main',
                        'marginTop' => '20px',
                        'marginBottom' => '20px',
                        'slots' => [[
                            'id' => $this->ids->id('cms-slot', ...$path),
                            'type' => 'text',
                            'slot' => 'content',
                            'config' => [
                                'content' => [
                                    'source' => 'static',
                                    'value' => '<h2>' . $child['name'] . '</h2>' . $child['body'],
                                ],
                                'verticalAlign' => ['source' => 'static', 'value' => null],
                            ],
                        ]],
                    ]],
                ]],
            ]], $context);

            $report->created('footer_page');
        } else {
            $report->reused('footer_page');
        }

        $this->categories->assignLayout(
            $context,
            $report,
            'footer_page_assignment',
            $categoryId,
            $pageId,
            // Only the placeholder we put there ourselves, or nothing at all.
            [null, $shopPageId]
        );
    }

    private function upsertRoot(Context $context, SeedReport $report, string $reportKey, string $name): string
    {
        return $this->categories->upsert(
            $context,
            $report,
            $reportKey,
            ['footer', $name],
            null,
            $name,
            [
                'active' => true,
                'visible' => true,
                'type' => CategoryDefinition::TYPE_PAGE,
                'displayNestedProducts' => false,
            ]
        );
    }

    /**
     * Sibling order is a linked list on `afterCategoryId`, not a position
     * column — `category` has no such column, and a `position` key in the
     * payload is quietly dropped, which is why an unordered footer looks like
     * it "just does not sort". The head of each list has no predecessor, so the
     * key is omitted rather than written as null.
     *
     * @return array<string, mixed>
     */
    private function pageFields(?string $shopPageId, ?string $afterCategoryId): array
    {
        $fields = [
            'active' => true,
            'visible' => true,
            'type' => CategoryDefinition::TYPE_PAGE,
            'displayNestedProducts' => false,
        ];

        if ($shopPageId !== null) {
            $fields['cmsPageId'] = $shopPageId;
        }

        if ($afterCategoryId !== null) {
            $fields['afterCategoryId'] = $afterCategoryId;
        }

        return $fields;
    }
}
