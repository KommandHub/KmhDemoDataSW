<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Seeder;

use Kommandhub\DemoData\Seeder\CrossSellingSeeder;
use Kommandhub\DemoData\Service\BatchWriter;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\DeterministicValueGenerator;
use Kommandhub\DemoData\Service\SeedReport;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class CrossSellingSeederWriteTest extends TestCase
{
    public function testWriteCreatesReusesAndEnrichesCrossSellTabs(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $seeder = new CrossSellingSeeder($repository, new BatchWriter(), new DemoIdGenerator(), new DeterministicValueGenerator());
        $context = Context::createDefaultContext();
        $report = new SeedReport();

        $existingFull = new ProductCrossSellingEntity();
        $existingFull->setId('existing-full');
        $existingFull->setAssignedProducts(new ProductCrossSellingAssignedProductsCollection([$this->assigned('item-a')]));

        $existingMissing = new ProductCrossSellingEntity();
        $existingMissing->setId('existing-missing');
        $existingMissing->setAssignedProducts(new ProductCrossSellingAssignedProductsCollection([$this->assigned('item-a')]));

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new ProductCrossSellingCollection([$existingFull, $existingMissing]));
        $repository->method('search')->willReturn($searchResult);

        $repository->expects($this->once())->method('create')->with($this->callback(static fn (array $batch): bool => $batch[0]['id'] === 'new-tab'), $context);
        $repository->expects($this->once())->method('update')->with($this->callback(static fn (array $batch): bool => $batch[0]['id'] === 'existing-missing'), $context);

        $payloads = [
            ['id' => 'new-tab', 'assignedProducts' => [['id' => 'item-a', 'productId' => 'p-a', 'position' => 1]]],
            ['id' => 'existing-full', 'assignedProducts' => [['id' => 'item-a', 'productId' => 'p-a', 'position' => 1]]],
            ['id' => 'existing-missing', 'assignedProducts' => [['id' => 'item-a', 'productId' => 'p-a', 'position' => 1], ['id' => 'item-b', 'productId' => 'p-b', 'position' => 2]]],
        ];

        $method = (new \ReflectionClass($seeder))->getMethod('write');
        $method->invoke($seeder, $context, $report, $payloads);

        $this->assertGreaterThan(0, $report->totalCreated());
        $this->assertGreaterThan(0, $report->totalEnriched());
    }

    public function testLoadReturnsEntitiesKeyedById(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $seeder = new CrossSellingSeeder($repository, new BatchWriter(), new DemoIdGenerator(), new DeterministicValueGenerator());
        $context = Context::createDefaultContext();

        $entity = new ProductCrossSellingEntity();
        $entity->setId('xsell-id');
        $searchResult = new EntitySearchResult('product_cross_selling', 1, new ProductCrossSellingCollection([$entity]), null, new Criteria(['xsell-id']), $context);
        $repository->method('search')->willReturn($searchResult);

        $method = (new \ReflectionClass($seeder))->getMethod('load');
        $loaded = $method->invoke($seeder, $context, ['xsell-id']);

        $this->assertArrayHasKey('xsell-id', $loaded);
    }

    private function assigned(string $id): ProductCrossSellingAssignedProductsEntity
    {
        $entity = new ProductCrossSellingAssignedProductsEntity();
        $entity->setId($id);

        return $entity;
    }
}
