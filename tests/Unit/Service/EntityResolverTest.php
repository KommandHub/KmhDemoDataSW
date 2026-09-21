<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Service;

use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\EntityResolver;
use Kommandhub\DemoData\Service\ResolvedEntity;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * Covers the decision the whole generator hangs on: given something that exists,
 * what — if anything — do we write to it?
 */
class EntityResolverTest extends TestCase
{
    private EntityResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new EntityResolver(new DemoIdGenerator());
    }

    public function testNothingIsWrittenWhenTheEntityIsAlreadyComplete(): void
    {
        $resolved = ResolvedEntity::owned('id-1', $this->category('Footwear', 'A description'));

        $this->assertNull($this->resolver->enrichmentPayload(
            $resolved,
            ['id' => 'id-1', 'name' => 'Footwear', 'description' => 'Generated description'],
            ['name', 'description']
        ));
    }

    public function testOnlyEmptyFieldsAreFilledIn(): void
    {
        $resolved = ResolvedEntity::owned('id-1', $this->category('Merchant Name', null));

        $payload = $this->resolver->enrichmentPayload(
            $resolved,
            ['id' => 'id-1', 'name' => 'Blueprint Name', 'description' => 'Generated description'],
            ['name', 'description']
        );

        $this->assertSame(
            ['id' => 'id-1', 'description' => 'Generated description'],
            $payload
        );
    }

    public function testEnrichmentUsesTheAdoptedIdNotTheDeterministicOne(): void
    {
        $resolved = ResolvedEntity::adopted('merchant-id', $this->category('Footwear', null));

        $payload = $this->resolver->enrichmentPayload(
            $resolved,
            ['id' => 'our-id', 'description' => 'Generated description'],
            ['description']
        );

        $this->assertNotNull($payload);
        $this->assertSame('merchant-id', $payload['id']);
    }

    public function testOverwriteFieldsAreWrittenWhenTheyDiffer(): void
    {
        $resolved = ResolvedEntity::owned('id-1', $this->category('Footwear', 'Old description'));

        $payload = $this->resolver->enrichmentPayload(
            $resolved,
            ['id' => 'id-1', 'description' => 'New description'],
            [],
            [],
            ['description']
        );

        $this->assertSame(['id' => 'id-1', 'description' => 'New description'], $payload);
    }

    public function testOnlyMissingAssociationLinksAreWritten(): void
    {
        $resolved = ResolvedEntity::owned('id-1', $this->categoryWithChildren(['linked-a']));

        $payload = $this->resolver->enrichmentPayload(
            $resolved,
            ['id' => 'id-1', 'children' => [['id' => 'linked-a'], ['id' => 'missing-b']]],
            [],
            ['children']
        );

        $this->assertSame(
            ['id' => 'id-1', 'children' => [['id' => 'missing-b']]],
            $payload
        );
    }

    public function testFullyLinkedAssociationsProduceNoWriteAtAll(): void
    {
        $resolved = ResolvedEntity::owned('id-1', $this->categoryWithChildren(['linked-a', 'linked-b']));

        $this->assertNull($this->resolver->enrichmentPayload(
            $resolved,
            ['id' => 'id-1', 'children' => [['id' => 'linked-a'], ['id' => 'linked-b']]],
            [],
            ['children']
        ));
    }

    public function testUnloadedAssociationFallsBackToWritingTheWholeList(): void
    {
        $resolved = ResolvedEntity::owned('id-1', $this->category('Footwear', 'desc'));

        $payload = $this->resolver->enrichmentPayload(
            $resolved,
            ['id' => 'id-1', 'children' => [['id' => 'a'], ['id' => 'b']]],
            [],
            ['children']
        );

        $this->assertNotNull($payload);
        $this->assertCount(2, $payload['children']);
    }

    public function testMissingOrMalformedAssociationPayloadProducesNoAssociationUpdate(): void
    {
        $resolved = ResolvedEntity::owned('id-1', $this->category('Footwear', 'desc'));

        $this->assertNull($this->resolver->enrichmentPayload($resolved, ['id' => 'id-1'], [], ['children']));
        $this->assertNull($this->resolver->enrichmentPayload($resolved, ['id' => 'id-1', 'children' => []], [], ['children']));
    }

    public function testMissingEntityExposesTheDeterministicId(): void
    {
        $resolved = ResolvedEntity::missing('our-id');

        $this->assertFalse($resolved->exists);
        $this->assertFalse($resolved->adopted);
        $this->assertSame('our-id', $resolved->id);
        $this->assertTrue($resolved->isEmpty('name'));
    }

    public function testResolveReturnsOwnedWhenFoundById(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = Context::createDefaultContext();

        $entity = new CategoryEntity();
        $entity->setId('id-1');

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new CategoryCollection([$entity]));
        $repository->method('search')->willReturn($searchResult);

        $resolved = $this->resolver->resolve($repository, $context, 'id-1', [], []);

        $this->assertTrue($resolved->exists);
        $this->assertFalse($resolved->adopted);
        $this->assertSame('id-1', $resolved->id);
    }

    public function testResolveReturnsMissingWhenNoNaturalKeyWasProvided(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = Context::createDefaultContext();

        $emptyResult = $this->createMock(EntitySearchResult::class);
        $emptyResult->method('getEntities')->willReturn(new CategoryCollection([]));
        $repository->method('search')->willReturn($emptyResult);

        $resolved = $this->resolver->resolve($repository, $context, 'deterministic-id');

        $this->assertFalse($resolved->exists);
        $this->assertSame('deterministic-id', $resolved->id);
    }

    public function testResolveReturnsAdoptedWhenFoundByNaturalKey(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = Context::createDefaultContext();

        $emptyResult = $this->createMock(EntitySearchResult::class);
        $emptyResult->method('getEntities')->willReturn(new CategoryCollection([]));

        $entity = new CategoryEntity();
        $entity->setId('natural-id');
        $foundResult = $this->createMock(EntitySearchResult::class);
        $foundResult->method('getEntities')->willReturn(new CategoryCollection([$entity]));

        $repository->method('search')->willReturnOnConsecutiveCalls($emptyResult, $foundResult);

        $resolved = $this->resolver->resolve($repository, $context, 'deterministic-id', [new EqualsFilter('name', 'Natural')], []);

        $this->assertTrue($resolved->exists);
        $this->assertTrue($resolved->adopted);
        $this->assertSame('natural-id', $resolved->id);
    }

    public function testResolveReturnsMissingWhenNeitherLookupFindsAnything(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = Context::createDefaultContext();

        $emptyResult = $this->createMock(EntitySearchResult::class);
        $emptyResult->method('getEntities')->willReturn(new CategoryCollection([]));
        $repository->method('search')->willReturn($emptyResult);

        $resolved = $this->resolver->resolve($repository, $context, 'deterministic-id', [new EqualsFilter('name', 'Natural')]);

        $this->assertFalse($resolved->exists);
        $this->assertSame('deterministic-id', $resolved->id);
    }

    public function testResolveByFieldAndIdsHelper(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = Context::createDefaultContext();

        $entity = new CategoryEntity();
        $entity->setId('id-1');

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new CategoryCollection([$entity]));
        $repository->method('search')->willReturn($searchResult);

        $resolved = $this->resolver->resolveByField($repository, $context, 'id-1', 'name', 'Footwear', ['children']);

        $this->assertTrue($resolved->exists);
        $this->assertInstanceOf(DemoIdGenerator::class, $this->resolver->ids());
    }

    /**
     * A null description is left *unset* rather than set to null: Shopware's
     * setter refuses null, and an unset property is exactly what an incomplete
     * row looks like when it comes back from the DAL.
     */
    private function category(string $name, ?string $description): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setId('id-1');
        $category->setName($name);

        if ($description !== null) {
            $category->setDescription($description);
        }

        return $category;
    }

    /**
     * @param array<int, string> $childIds
     */
    private function categoryWithChildren(array $childIds): CategoryEntity
    {
        $category = $this->category('Footwear', 'desc');

        $children = new CategoryCollection();

        foreach ($childIds as $childId) {
            $child = new CategoryEntity();
            $child->setId($childId);
            $child->setUniqueIdentifier($childId);
            $children->add($child);
        }

        $category->setChildren($children);

        return $category;
    }
}
