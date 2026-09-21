<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Seeder;

use Kommandhub\DemoData\Seeder\ProductSeeder;
use Kommandhub\DemoData\Service\BatchWriter;
use Kommandhub\DemoData\Service\ChannelPricing;
use Kommandhub\DemoData\Service\EntityResolver;
use Kommandhub\DemoData\Service\ProductNameGenerator;
use Kommandhub\DemoData\Service\ProductPayloadBuilder;
use Kommandhub\DemoData\Service\ResolvedEntity;
use Kommandhub\DemoData\Service\SeedReport;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class ProductSeederTest extends TestCase
{
    public function testSeedCreatesOwnedProducts(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $payloadBuilder = $this->createMock(ProductPayloadBuilder::class);
        $names = $this->createMock(ProductNameGenerator::class);
        $resolver = $this->createMock(EntityResolver::class);
        $pricing = ChannelPricing::of('rule-id', 1.0, []);

        $seeder = new ProductSeeder($repository, $payloadBuilder, $names, new BatchWriter(), $resolver);
        $context = Context::createDefaultContext();
        $report = new SeedReport();

        $names->method('names')->willReturn(['Product 1']);
        $payloadBuilder->method('build')->willReturn([
            'id' => 'p1',
            'productNumber' => 'PN1',
            'children' => [['id' => 'v1', 'productNumber' => 'PN1.1']],
            'productReviews' => [['id' => 'r1']],
        ]);
        $resolver->method('resolve')->willReturn(ResolvedEntity::missing('p1'));

        $repository->expects($this->once())->method('create')->with($this->callback(static fn (array $batch): bool => $batch[0]['id'] === 'p1'), $this->identicalTo($context));

        $result = $seeder->seed($context, $report, 'flagship', 'sc-id', [$this->leaf()], ['m1' => 'm1-id'], ['m1' => ['name' => 'Manufacturer 1']], [], [], [], 'tax-id', 19.0, 1, $pricing);

        $this->assertCount(1, $result);
        $this->assertSame(['p1'], $result[0]['productIds']);
    }

    public function testSeedSkipsCategoryWithUnknownManufacturer(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $payloadBuilder = $this->createMock(ProductPayloadBuilder::class);
        $names = $this->createMock(ProductNameGenerator::class);
        $resolver = $this->createMock(EntityResolver::class);
        $seeder = new ProductSeeder($repository, $payloadBuilder, $names, new BatchWriter(), $resolver);
        $names->method('names')->willReturn(['Product 1']);
        $repository->expects($this->never())->method('create');

        $report = new SeedReport();
        $result = $seeder->seed(Context::createDefaultContext(), $report, 'flagship', 'sc-id', [$this->leaf()], [], [], [], [], [], 'tax-id', 19.0, 1, ChannelPricing::of('rule-id', 1.0, []));

        $this->assertSame([], $result);
        $this->assertStringContainsString('unknown manufacturer', implode("\n", $report->notes()));
    }

    public function testSeedUpdatesExistingOwnedAndAdoptedProducts(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $payloadBuilder = $this->createMock(ProductPayloadBuilder::class);
        $names = $this->createMock(ProductNameGenerator::class);
        $resolver = $this->createMock(EntityResolver::class);
        $seeder = new ProductSeeder($repository, $payloadBuilder, $names, new BatchWriter(), $resolver);
        $context = Context::createDefaultContext();

        $names->method('names')->willReturn(['Product 1', 'Product 2']);
        $payloadBuilder->method('build')->willReturnOnConsecutiveCalls(
            ['id' => 'p1', 'productNumber' => 'PN1', 'media' => [['id' => 'row-1', 'mediaId' => 'media-new']], 'productReviews' => [['id' => 'r1']], 'children' => []],
            ['id' => 'p2', 'productNumber' => 'PN2', 'media' => [['id' => 'row-1', 'mediaId' => 'media-new']], 'productReviews' => [['id' => 'r2']], 'children' => [['id' => 'v2', 'productNumber' => 'PN2.1']]]
        );

        $owned = new ProductEntity();
        $owned->setId('p1');
        $adopted = new ProductEntity();
        $adopted->setId('p2');
        $resolver->method('resolve')->willReturnOnConsecutiveCalls(
            ResolvedEntity::owned('p1', $owned),
            ResolvedEntity::adopted('p2', $adopted)
        );
        $resolver->method('enrichmentPayload')->willReturnOnConsecutiveCalls(null, ['id' => 'p1'], ['id' => 'p2', 'description' => 'Enriched']);
        $repository->expects($this->once())->method('update');

        $seeder->seed($context, new SeedReport(), 'flagship', 'sc-id', [$this->leaf()], ['m1' => 'm1-id'], ['m1' => ['name' => 'Manufacturer 1']], [], [], [], 'tax-id', 19.0, 2, ChannelPricing::of('rule-id', 1.0, []));
    }

    public function testSeedCollectsChildrenForOwnedProducts(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $payloadBuilder = $this->createMock(ProductPayloadBuilder::class);
        $names = $this->createMock(ProductNameGenerator::class);
        $resolver = $this->createMock(EntityResolver::class);
        $seeder = new ProductSeeder($repository, $payloadBuilder, $names, new BatchWriter(), $resolver);
        $context = Context::createDefaultContext();

        $names->method('names')->willReturn(['Product 1']);
        $payloadBuilder->method('build')->willReturn([
            'id' => 'p1',
            'productNumber' => 'PN1',
            'children' => [['id' => 'v1', 'productNumber' => 'PN1.1']],
            'productReviews' => [],
        ]);
        $existing = new ProductEntity();
        $existing->setId('p1');
        $variant = new ProductEntity();
        $variant->setId('v1');
        $resolver->method('resolve')->willReturn(ResolvedEntity::owned('p1', $existing));
        $resolver->method('enrichmentPayload')->willReturnOnConsecutiveCalls(null, ['id' => 'v1', 'description' => 'Variant']);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new ProductCollection([$variant]));
        $repository->method('search')->willReturn($searchResult);
        $repository->expects($this->once())->method('update');

        $seeder->seed($context, new SeedReport(), 'flagship', 'sc-id', [$this->leaf()], ['m1' => 'm1-id'], ['m1' => ['name' => 'Manufacturer 1']], [], [], [], 'tax-id', 19.0, 1, ChannelPricing::of('rule-id', 1.0, []));
    }

    public function testGalleryDiffersCoversAllBranches(): void
    {
        $seeder = new ProductSeeder(
            $this->createMock(EntityRepository::class),
            $this->createMock(ProductPayloadBuilder::class),
            $this->createMock(ProductNameGenerator::class),
            new BatchWriter(),
            $this->createMock(EntityResolver::class)
        );
        $method = (new \ReflectionClass($seeder))->getMethod('galleryDiffers');

        $product = new ProductEntity();
        $product->setId('p1');
        $resolved = ResolvedEntity::owned('p1', $product);

        $this->assertFalse($method->invoke($seeder, $resolved, []));
        $this->assertTrue($method->invoke($seeder, $resolved, ['media' => [['id' => 'row-1', 'mediaId' => 'm1']]]));

        $mediaRow = new ProductMediaEntity();
        $mediaRow->setId('row-1');
        $mediaRow->setMediaId('m1');
        $collection = new ProductMediaCollection([$mediaRow]);
        $product->setMedia($collection);

        $this->assertFalse($method->invoke($seeder, $resolved, ['media' => [['id' => 'row-1', 'mediaId' => 'm1']]]));
        $this->assertTrue($method->invoke($seeder, $resolved, ['media' => [['id' => 'row-1', 'mediaId' => 'm2']]]));
        $this->assertTrue($method->invoke($seeder, $resolved, ['media' => [['mediaId' => 'm1']]]));
    }

    public function testCollectChildrenCreatesMissingVariantsAndEnrichesExistingOnes(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $resolver = $this->createMock(EntityResolver::class);
        $seeder = new ProductSeeder(
            $repository,
            $this->createMock(ProductPayloadBuilder::class),
            $this->createMock(ProductNameGenerator::class),
            new BatchWriter(),
            $resolver
        );
        $context = Context::createDefaultContext();
        $report = new SeedReport();

        $existing = new ProductEntity();
        $existing->setId('v1');
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new ProductCollection([$existing]));
        $repository->method('search')->willReturn($searchResult);
        $resolver->method('enrichmentPayload')->willReturn(['id' => 'v1', 'description' => 'Variant']);

        $creates = [];
        $updates = [];
        $method = (new \ReflectionClass($seeder))->getMethod('collectChildren');
        $children = [
            ['id' => 'v1', 'productNumber' => 'PN1.1'],
            ['id' => 'v2', 'productNumber' => 'PN1.2'],
        ];
        $method->invokeArgs($seeder, [$context, $report, $children, &$creates, &$updates]);

        $this->assertCount(1, $creates);
        $this->assertSame('v2', $creates[0]['id']);
        $this->assertCount(1, $updates);
        $this->assertSame('v1', $updates[0]['id']);
    }

    /** @return array{id: string, name: string, path: array<int, string>, ancestorIds: array<int, string>, spec: array<string, mixed>} */
    private function leaf(): array
    {
        return [
            'id' => 'c1',
            'name' => 'Category 1',
            'path' => ['Root', 'Category 1'],
            'ancestorIds' => ['root-id'],
            'spec' => ['manufacturer' => 'm1'],
        ];
    }
}
