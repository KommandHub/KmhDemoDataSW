<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Seeder;

use Kommandhub\DemoData\Service\SeedReport;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\Context;

/**
 * Builds one category tree per sales channel, underneath that channel's root.
 *
 * Resolution and writing live in CategoryWriter, which every category in this
 * plugin goes through. What is left here is the walk and the payload: what a
 * catalogue category looks like, and which nodes are leaves.
 *
 * Returns the leaf nodes, because leaves are where products live — the caller
 * never has to walk the tree a second time.
 */
class CategorySeeder
{
    public function __construct(private readonly CategoryWriter $categories)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $tree
     * @param array<string, string> $mediaIds relative media path => media id
     * @param array<string, string> $photoMediaIds manifest photo id => media id
     *
     * @return array<int, array{id: string, name: string, path: array<int, string>, ancestorIds: array<int, string>, spec: array<string, mixed>}>
     */
    public function seed(
        Context $context,
        SeedReport $report,
        string $channelKey,
        string $rootCategoryId,
        array $tree,
        array $mediaIds,
        array $photoMediaIds = []
    ): array {
        // Listings get the layout with the filter sidebar: a catalogue this size
        // is unusable without filters within reach.
        $listingPageId = $this->categories->defaultCmsPageId($context, 'product_list', 'sidebar');
        $leaves = [];

        $this->walk($context, $report, $rootCategoryId, $tree, [$channelKey], [], $listingPageId, $mediaIds, $photoMediaIds, $leaves);

        return $leaves;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param array<int, string> $path
     * @param array<int, string> $ancestorIds
     * @param array<string, string> $mediaIds
     * @param array<int, array{id: string, name: string, path: array<int, string>, ancestorIds: array<int, string>, spec: array<string, mixed>}> $leaves
     */
    private function walk(
        Context $context,
        SeedReport $report,
        string $parentId,
        array $nodes,
        array $path,
        array $ancestorIds,
        ?string $listingPageId,
        array $mediaIds,
        array $photoMediaIds,
        array &$leaves
    ): void {
        $previous = null;

        foreach ($nodes as $node) {
            /** @var string $name */
            $name = $node['name'];
            $nodePath = [...$path, $name];

            $categoryId = $this->categories->upsert(
                $context,
                $report,
                'category',
                $nodePath,
                $parentId,
                $name,
                $this->fields(
                    $name,
                    $listingPageId,
                    isset($node['image']) && \is_string($node['image']) ? ($mediaIds[$node['image']] ?? null) : null,
                    $previous
                ),
                ['description', 'metaTitle', 'metaDescription', 'mediaId', 'afterCategoryId'],
                ['cmsPageId']
            );

            $previous = $categoryId;

            /** @var array<int, array<string, mixed>> $children */
            $children = \is_array($node['children'] ?? null) ? $node['children'] : [];

            if ($children !== []) {
                $this->walk(
                    $context,
                    $report,
                    $categoryId,
                    $children,
                    $nodePath,
                    [...$ancestorIds, $categoryId],
                    $listingPageId,
                    $mediaIds,
                    $photoMediaIds,
                    $leaves
                );

                continue;
            }

            /** @var array<int, string> $photos */
            $photos = \is_array($node['photos'] ?? null) ? $node['photos'] : [];
            $node['photoMediaIds'] = array_values(array_intersect_key($photoMediaIds, array_flip($photos)));

            $leaves[] = [
                'id' => $categoryId,
                'name' => $name,
                'path' => $nodePath,
                'ancestorIds' => [...$ancestorIds, $categoryId],
                'spec' => $node,
            ];
        }
    }

    /**
     * Sibling order is the `afterCategoryId` linked list; `category` has no
     * position column, and a `position` key is silently dropped.
     *
     * @return array<string, mixed>
     */
    private function fields(string $name, ?string $listingPageId, ?string $mediaId, ?string $afterCategoryId): array
    {
        $fields = [
            'active' => true,
            'visible' => true,
            'type' => CategoryDefinition::TYPE_PAGE,
            'displayNestedProducts' => true,
            'description' => $this->description($name),
            'metaTitle' => $name,
            'metaDescription' => \sprintf('Browse %s — curated demo assortment.', $name),
        ];

        if ($mediaId !== null) {
            $fields['mediaId'] = $mediaId;
        }

        if ($listingPageId !== null) {
            $fields['cmsPageId'] = $listingPageId;
        }

        if ($afterCategoryId !== null) {
            $fields['afterCategoryId'] = $afterCategoryId;
        }

        return $fields;
    }

    private function description(string $name): string
    {
        return \sprintf(
            '<p>Everything filed under %s, from everyday basics to the pieces worth saving for. '
            . 'Filter by the properties that matter and compare like for like.</p>',
            $name
        );
    }
}
