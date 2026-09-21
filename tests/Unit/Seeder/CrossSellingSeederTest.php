<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Seeder;

use Kommandhub\DemoData\Seeder\CrossSellingSeeder;
use Kommandhub\DemoData\Service\BatchWriter;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\DeterministicValueGenerator;
use Kommandhub\DemoData\Service\SeedReport;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * Cross-selling is where a demo catalogue most easily embarrasses itself: a
 * product listed as similar to itself, two tabs showing the same four items, or
 * a tab pointing at products the shopper's storefront cannot show.
 */
class CrossSellingSeederTest extends TestCase
{
    private EntityRepository&MockObject $repository;
    private CrossSellingSeeder $seeder;
    private Context $context;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->seeder = new CrossSellingSeeder(
            $this->repository,
            new BatchWriter(),
            new DemoIdGenerator(),
            new DeterministicValueGenerator()
        );
        $this->context = Context::createDefaultContext();
    }

    public function testNoProductCrossSellsItself(): void
    {
        foreach ($this->created($this->catalogue()) as $tab) {
            $assigned = array_column($tab['assignedProducts'], 'productId');

            $this->assertNotContains($tab['productId'], $assigned, 'product cross-sells itself');
        }
    }

    public function testTheTwoTabsDoNotShowTheSameProducts(): void
    {
        $byProduct = [];

        foreach ($this->created($this->catalogue()) as $tab) {
            $byProduct[$tab['productId']][$tab['name']] = array_column($tab['assignedProducts'], 'productId');
        }

        $compared = 0;

        foreach ($byProduct as $tabs) {
            if (\count($tabs) < 2) {
                continue;
            }

            ++$compared;
            $this->assertSame(
                [],
                array_intersect($tabs['Similar products'], $tabs['Customers also bought']),
                'the two tabs overlap, so they render as duplicates'
            );
        }

        $this->assertGreaterThan(0, $compared);
    }

    public function testSimilarProductsComeFromTheSameLeafCategory(): void
    {
        $tabs = $this->created($this->catalogue());
        $coffee = ['coffee-1', 'coffee-2', 'coffee-3', 'coffee-4'];

        foreach ($tabs as $tab) {
            if ($tab['name'] !== 'Similar products' || !\in_array($tab['productId'], $coffee, true)) {
                continue;
            }

            foreach (array_column($tab['assignedProducts'], 'productId') as $assigned) {
                $this->assertContains($assigned, $coffee);
            }
        }
    }

    public function testAlsoBoughtReachesIntoSiblingCategories(): void
    {
        $tabs = $this->created($this->catalogue());
        $water = ['water-1', 'water-2', 'water-3', 'water-4'];
        $checked = 0;

        foreach ($tabs as $tab) {
            if ($tab['name'] !== 'Customers also bought' || $tab['productId'] !== 'coffee-1') {
                continue;
            }

            ++$checked;

            foreach (array_column($tab['assignedProducts'], 'productId') as $assigned) {
                $this->assertContains($assigned, $water, 'also-bought should reach the sibling leaf');
            }
        }

        $this->assertSame(1, $checked);
    }

    /**
     * A lone leaf under its top-level category has no sibling pool, so there is
     * nothing to put in a second tab — better no tab than an empty one.
     */
    public function testALoneCategoryProducesNoEmptyTab(): void
    {
        $written = [[
            'leaf' => $this->leaf('solo', 'solo-top'),
            'productIds' => ['solo-1', 'solo-2', 'solo-3', 'solo-4'],
        ]];

        foreach ($this->created($written) as $tab) {
            $this->assertNotEmpty($tab['assignedProducts'], $tab['name'] . ' is empty');
        }
    }

    public function testASingleProductCategoryCrossSellsNothing(): void
    {
        $written = [[
            'leaf' => $this->leaf('lonely', 'lonely-top'),
            'productIds' => ['only-1'],
        ]];

        $this->repository->expects($this->never())->method('create');
        $this->repository->expects($this->never())->method('update');

        $this->seeder->seed($this->context, new SeedReport(), $written);
    }

    public function testTabsAreStableAcrossRuns(): void
    {
        $this->assertEquals($this->created($this->catalogue()), $this->created($this->catalogue()));
    }

    /**
     * Runs the seeder against an installation that holds nothing yet, and
     * returns the payloads it tried to create.
     *
     * @param array<int, array{leaf: array<string, mixed>, productIds: array<int, string>}> $written
     *
     * @return array<int, array<string, mixed>>
     */
    private function created(array $written): array
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            fn (Criteria $criteria, Context $context): EntitySearchResult => new EntitySearchResult(
                'product_cross_selling',
                0,
                new ProductCrossSellingCollection(),
                null,
                $criteria,
                $context
            )
        );

        $created = [];
        $repository->method('create')->willReturnCallback(
            static function (array $payloads) use (&$created): EntityWrittenContainerEvent {
                $created = [...$created, ...$payloads];

                return EntityWrittenContainerEvent::createWithWrittenEvents([], Context::createDefaultContext(), []);
            }
        );

        $seeder = new CrossSellingSeeder($repository, new BatchWriter(), new DemoIdGenerator(), new DeterministicValueGenerator());
        $seeder->seed($this->context, new SeedReport(), $written);

        return $created;
    }

    /**
     * Two leaves under one top-level category, plus a third elsewhere.
     *
     * @return array<int, array{leaf: array<string, mixed>, productIds: array<int, string>}>
     */
    private function catalogue(): array
    {
        return [
            [
                'leaf' => $this->leaf('coffee', 'beverages'),
                'productIds' => ['coffee-1', 'coffee-2', 'coffee-3', 'coffee-4'],
            ],
            [
                'leaf' => $this->leaf('water', 'beverages'),
                'productIds' => ['water-1', 'water-2', 'water-3', 'water-4'],
            ],
            [
                'leaf' => $this->leaf('rice', 'pantry'),
                'productIds' => ['rice-1', 'rice-2', 'rice-3', 'rice-4'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function leaf(string $id, string $topLevelId): array
    {
        return [
            'id' => $id,
            'name' => ucfirst($id),
            'path' => ['channel', $topLevelId, $id],
            'ancestorIds' => [$topLevelId, $id],
            'spec' => [],
        ];
    }
}
