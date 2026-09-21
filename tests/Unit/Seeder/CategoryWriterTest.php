<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Seeder;

use Kommandhub\DemoData\Seeder\CategoryWriter;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\EntityResolver;
use Kommandhub\DemoData\Service\ResolvedEntity;
use Kommandhub\DemoData\Service\SeedReport;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class CategoryWriterTest extends TestCase
{
    public function testUpsertNewCategory(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $cmsPageRepository = $this->createMock(EntityRepository::class);
        $resolver = $this->createMock(EntityResolver::class);
        $ids = new DemoIdGenerator();
        $resolver->method('ids')->willReturn($ids);

        $writer = new CategoryWriter($categoryRepository, $cmsPageRepository, $resolver);
        $context = Context::createDefaultContext();
        $report = new SeedReport();

        $resolver->method('resolve')->willReturn(ResolvedEntity::missing('cat-id'));
        $categoryRepository->expects($this->once())->method('create');

        $result = $writer->upsert($context, $report, 'cat', ['path'], 'parent-id', 'Name', [], []);

        $this->assertSame('cat-id', $result);
    }

    public function testUpsertExistingCategory(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $cmsPageRepository = $this->createMock(EntityRepository::class);
        $resolver = $this->createMock(EntityResolver::class);
        $ids = new DemoIdGenerator();
        $resolver->method('ids')->willReturn($ids);

        $writer = new CategoryWriter($categoryRepository, $cmsPageRepository, $resolver);
        $context = Context::createDefaultContext();
        $report = new SeedReport();

        $existing = new CategoryEntity();
        $existing->setId('cat-id');
        $resolver->method('resolve')->willReturn(ResolvedEntity::owned('cat-id', $existing));
        $resolver->method('enrichmentPayload')->willReturn(['id' => 'cat-id', 'name' => 'New Name']);

        $categoryRepository->expects($this->once())->method('update');

        $result = $writer->upsert($context, $report, 'cat', ['path'], 'parent-id', 'Name', [], []);

        $this->assertSame('cat-id', $result);
    }

    public function testFillLayoutIfEmptyUpdatesOnlyWhenThereIsNoLayoutAndADefaultExists(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $cmsPageRepository = $this->createMock(EntityRepository::class);
        $resolver = $this->createMock(EntityResolver::class);
        $resolver->method('ids')->willReturn(new DemoIdGenerator());

        $writer = new CategoryWriter($categoryRepository, $cmsPageRepository, $resolver);
        $context = Context::createDefaultContext();
        $report = new SeedReport();

        $emptyLayout = $this->createMock(IdSearchResult::class);
        $emptyLayout->method('firstId')->willReturn('cat-id');
        $defaultCms = $this->createMock(IdSearchResult::class);
        $defaultCms->method('firstId')->willReturn('default-cms-id');

        $categoryRepository->method('searchIds')->willReturn($emptyLayout);
        $cmsPageRepository->method('searchIds')->willReturn($defaultCms);
        $categoryRepository->expects($this->once())->method('update')->with(
            [['id' => 'cat-id', 'cmsPageId' => 'default-cms-id']],
            $context
        );

        $writer->fillLayoutIfEmpty($context, $report, 'cat', 'cat-id', 'product_list');
    }

    public function testFillLayoutIfEmptyReturnsWhenCategoryAlreadyHasLayoutOrNoDefaultExists(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $cmsPageRepository = $this->createMock(EntityRepository::class);
        $resolver = $this->createMock(EntityResolver::class);
        $resolver->method('ids')->willReturn(new DemoIdGenerator());
        $writer = new CategoryWriter($categoryRepository, $cmsPageRepository, $resolver);
        $context = Context::createDefaultContext();

        $hasLayout = $this->createMock(IdSearchResult::class);
        $hasLayout->method('firstId')->willReturn(null);
        $categoryRepository->method('searchIds')->willReturn($hasLayout);
        $categoryRepository->expects($this->never())->method('update');
        $writer->fillLayoutIfEmpty($context, new SeedReport(), 'cat', 'cat-id', 'product_list');

        $categoryRepository2 = $this->createMock(EntityRepository::class);
        $cmsPageRepository2 = $this->createMock(EntityRepository::class);
        $writer2 = new CategoryWriter($categoryRepository2, $cmsPageRepository2, $resolver);
        $categoryRepository2->method('searchIds')->willReturn($this->idResult('cat-id'));
        $cmsPageRepository2->method('searchIds')->willReturn($this->idResult(null));
        $categoryRepository2->expects($this->never())->method('update');
        $writer2->fillLayoutIfEmpty($context, new SeedReport(), 'cat', 'cat-id', 'product_list');
    }

    public function testAssignLayoutReusesWhenAlreadyAssigned(): void
    {
        $writer = $this->writerForCurrentLayout('new-id');
        $writer[0]->expects($this->never())->method('update');

        $writer[3]->assignLayout($writer[4], new SeedReport(), 'cat', 'cat-id', 'new-id', ['old-id']);
    }

    public function testAssignLayoutSkipsWhenCurrentLayoutIsNotReplaceable(): void
    {
        $writer = $this->writerForCurrentLayout('merchant-layout');
        $writer[0]->expects($this->never())->method('update');

        $writer[3]->assignLayout($writer[4], new SeedReport(), 'cat', 'cat-id', 'new-id', ['old-id']);
    }

    public function testAssignLayoutUpdatesWhenCurrentLayoutIsReplaceable(): void
    {
        $writer = $this->writerForCurrentLayout('old-id');
        $writer[0]->expects($this->once())->method('update')->with([['id' => 'cat-id', 'cmsPageId' => 'new-id']], $writer[4]);

        $writer[3]->assignLayout($writer[4], new SeedReport(), 'cat', 'cat-id', 'new-id', ['old-id']);
    }

    public function testAssignLayoutTreatsAnEmptyStoredLayoutAsReplaceableNull(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $cmsPageRepository = $this->createMock(EntityRepository::class);
        $resolver = $this->createMock(EntityResolver::class);
        $resolver->method('ids')->willReturn(new DemoIdGenerator());
        $writer = new CategoryWriter($categoryRepository, $cmsPageRepository, $resolver);
        $context = Context::createDefaultContext();

        $categoryRepository->method('searchIds')->willReturn($this->idResult('cat-id'));
        $categoryRepository->expects($this->once())->method('update')->with([['id' => 'cat-id', 'cmsPageId' => 'new-id']], $context);

        $writer->assignLayout($context, new SeedReport(), 'cat', 'cat-id', 'new-id', [null]);
    }

    public function testDefaultCmsPageIdFallsBackToAnyUnlockedPage(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $cmsPageRepository = $this->createMock(EntityRepository::class);
        $resolver = $this->createMock(EntityResolver::class);
        $resolver->method('ids')->willReturn(new DemoIdGenerator());
        $writer = new CategoryWriter($categoryRepository, $cmsPageRepository, $resolver);

        $cmsPageRepository->method('searchIds')->willReturnOnConsecutiveCalls(
            $this->idResult(null),
            $this->idResult('fallback-page')
        );

        $this->assertSame('fallback-page', $writer->defaultCmsPageId(Context::createDefaultContext(), 'product_list'));
    }

    private function idResult(?string $firstId): IdSearchResult
    {
        $result = $this->createMock(IdSearchResult::class);
        $result->method('firstId')->willReturn($firstId);

        return $result;
    }

    /** @return array{0: EntityRepository, 1: EntityRepository, 2: EntityResolver, 3: CategoryWriter, 4: Context} */
    private function writerForCurrentLayout(string $currentLayout): array
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $cmsPageRepository = $this->createMock(EntityRepository::class);
        $resolver = $this->createMock(EntityResolver::class);
        $resolver->method('ids')->willReturn(new DemoIdGenerator());
        $writer = new CategoryWriter($categoryRepository, $cmsPageRepository, $resolver);
        $context = Context::createDefaultContext();

        $categoryRepository->method('searchIds')->willReturn($this->idResult(null));
        $category = new CategoryEntity();
        $category->setId('cat-id');
        $category->setCmsPageId($currentLayout);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new CategoryCollection([$category]));
        $categoryRepository->method('search')->willReturn($searchResult);

        return [$categoryRepository, $cmsPageRepository, $resolver, $writer, $context];
    }
}
