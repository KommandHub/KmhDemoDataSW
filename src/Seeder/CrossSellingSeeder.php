<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Seeder;

use Kommandhub\DemoData\Service\BatchWriter;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\DeterministicValueGenerator;
use Kommandhub\DemoData\Service\SeedReport;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * Gives each product its "Customers also bought" and "Similar products" tabs.
 *
 * This runs after the products exist, because it needs their ids — a product
 * cannot be cross-sold against siblings that have not been written yet. That is
 * also why it is a separate seeder rather than part of the product payload.
 *
 * The two tabs pull from deliberately different pools: "similar" stays inside
 * the product's own leaf category, "also bought" reaches across to sibling
 * leaves under the same top-level category. Both drawn from the same pool would
 * render two tabs showing the same four products, which looks like a bug.
 *
 * Manual product lists rather than product streams: a stream is a stored filter
 * that re-evaluates, so its contents depend on indexer state and it demos badly.
 */
class CrossSellingSeeder
{
    private const MIN_ITEMS = 3;
    private const MAX_ITEMS = 6;
    private const BATCH_SIZE = 50;

    /**
     * @param EntityRepository<ProductCrossSellingCollection> $crossSellingRepository
     */
    public function __construct(
        private readonly EntityRepository $crossSellingRepository,
        private readonly BatchWriter $batch,
        private readonly DemoIdGenerator $ids,
        private readonly DeterministicValueGenerator $values
    ) {
    }

    /**
     * @param array<int, array{leaf: array{id: string, name: string, path: array<int, string>, ancestorIds: array<int, string>, spec: array<string, mixed>}, productIds: array<int, string>}> $written
     */
    public function seed(Context $context, SeedReport $report, array $written): void
    {
        $byTopLevel = $this->groupByTopLevelCategory($written);
        $payloads = [];

        foreach ($written as $group) {
            $leafPool = $group['productIds'];
            $siblingPool = array_values(array_diff(
                $byTopLevel[$group['leaf']['ancestorIds'][0] ?? ''] ?? [],
                $leafPool
            ));

            foreach ($leafPool as $productId) {
                $payloads = [
                    ...$payloads,
                    ...$this->tabsFor(
                        $productId,
                        array_values(array_diff($leafPool, [$productId])),
                        $siblingPool
                    ),
                ];
            }
        }

        $this->write($context, $report, $payloads);
    }

    /**
     * @param array<int, string> $sameCategory
     * @param array<int, string> $siblingCategories
     *
     * @return array<int, array<string, mixed>>
     */
    private function tabsFor(string $productId, array $sameCategory, array $siblingCategories): array
    {
        $tabs = [];

        // Falls back to the sibling pool when a leaf is too small to fill a
        // "similar" tab on its own, rather than rendering an empty tab.
        $similarPool = \count($sameCategory) >= self::MIN_ITEMS ? $sameCategory : $siblingCategories;
        $boughtPool = $siblingCategories !== [] ? $siblingCategories : $sameCategory;

        $similar = $this->pick($productId, 'similar', $similarPool);

        if ($similar !== []) {
            $tabs[] = $this->tab($productId, 'similar', 'Similar products', 1, $similar);
        }

        $bought = $this->pick($productId, 'also-bought', array_values(array_diff($boughtPool, $similar)));

        if ($bought !== []) {
            $tabs[] = $this->tab($productId, 'also-bought', 'Customers also bought', 2, $bought);
        }

        return $tabs;
    }

    /**
     * @param array<int, string> $pool
     *
     * @return array<int, string>
     */
    private function pick(string $productId, string $tabKey, array $pool): array
    {
        if ($pool === []) {
            return [];
        }

        return $this->values->pickMany(
            $productId . '|xsell|' . $tabKey,
            $pool,
            $this->values->int($productId . '|xsell-count|' . $tabKey, self::MIN_ITEMS, self::MAX_ITEMS)
        );
    }

    /**
     * @param array<int, string> $assigned
     *
     * @return array<string, mixed>
     */
    private function tab(string $productId, string $tabKey, string $name, int $position, array $assigned): array
    {
        $crossSellingId = $this->ids->id('cross-selling', $productId, $tabKey);

        return [
            'id' => $crossSellingId,
            'productId' => $productId,
            'name' => $name,
            'position' => $position,
            'type' => ProductCrossSellingDefinition::TYPE_PRODUCT_LIST,
            'sortBy' => ProductCrossSellingDefinition::SORT_BY_NAME,
            'sortDirection' => 'ASC',
            'limit' => 24,
            'active' => true,
            'assignedProducts' => $this->assignments($crossSellingId, $assigned),
        ];
    }

    /**
     * @param array<int, string> $assigned
     *
     * @return array<int, array{id: string, productId: string, position: int}>
     */
    private function assignments(string $crossSellingId, array $assigned): array
    {
        $position = 0;

        return array_map(
            fn (string $assignedId): array => [
                'id' => $this->ids->id('cross-selling-item', $crossSellingId, $assignedId),
                'productId' => $assignedId,
                'position' => ++$position,
            ],
            $assigned
        );
    }

    /**
     * @param array<int, array{leaf: array{ancestorIds: array<int, string>}, productIds: array<int, string>}> $written
     *
     * @return array<string, array<int, string>>
     */
    private function groupByTopLevelCategory(array $written): array
    {
        $grouped = [];

        foreach ($written as $group) {
            $topLevelId = $group['leaf']['ancestorIds'][0] ?? '';
            $grouped[$topLevelId] = [...$grouped[$topLevelId] ?? [], ...$group['productIds']];
        }

        return $grouped;
    }

    /**
     * @param array<int, array<string, mixed>> $payloads
     */
    private function write(Context $context, SeedReport $report, array $payloads): void
    {
        if ($payloads === []) {
            return;
        }

        /** @var array<int, string> $ids */
        $ids = array_column($payloads, 'id');
        $existing = $this->load($context, $ids);

        $creates = [];
        $updates = [];

        foreach ($payloads as $payload) {
            /** @var string $id */
            $id = $payload['id'];
            $current = $existing[$id] ?? null;

            if ($current === null) {
                $creates[] = $payload;
                $report->created('cross_selling');

                continue;
            }

            $report->reused('cross_selling');

            $linked = $current->getAssignedProducts()?->getIds() ?? [];
            /** @var array<int, array{id: string, productId: string, position: int}> $assigned */
            $assigned = $payload['assignedProducts'];
            $missing = array_values(array_filter(
                $assigned,
                static fn (array $item): bool => !\in_array($item['id'], $linked, true)
            ));

            if ($missing === []) {
                continue;
            }

            $updates[] = ['id' => $id, 'assignedProducts' => $missing];
            $report->enriched('cross_selling');
        }

        $this->batch->create($this->crossSellingRepository, $creates, $context, self::BATCH_SIZE);
        $this->batch->update($this->crossSellingRepository, $updates, $context, self::BATCH_SIZE);
    }

    /**
     * One query for the whole channel rather than one per product.
     *
     * @param array<int, string> $ids
     *
     * @return array<string, ProductCrossSellingEntity>
     */
    private function load(Context $context, array $ids): array
    {
        $criteria = new Criteria($ids);
        $criteria->addAssociation('assignedProducts');
        $criteria->setLimit(\count($ids));

        $existing = [];
        $found = $this->crossSellingRepository->search($criteria, $context)->getEntities();

        foreach ($found as $entity) {
            /** @var ProductCrossSellingEntity $entity */
            $existing[$entity->getId()] = $entity;
        }

        return $existing;
    }
}
