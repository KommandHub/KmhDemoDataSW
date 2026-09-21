<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Seeder;

use Kommandhub\DemoData\Seeder\CategoryWriter;
use Kommandhub\DemoData\Seeder\FooterSeeder;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\SeedReport;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class FooterSeederTest extends TestCase
{
    public function testSeedCreatesFooterPages(): void
    {
        $categories = $this->createMock(CategoryWriter::class);
        $cmsPageRepository = $this->createMock(EntityRepository::class);
        $seeder = new FooterSeeder($categories, $cmsPageRepository, new DemoIdGenerator());
        $context = Context::createDefaultContext();
        $report = new SeedReport();

        $channels = [['id' => 'sc1', 'name' => 'Store 1', 'url' => 'http://store1.com']];

        $categories->method('upsert')->willReturn('category-id');
        $categories->method('defaultCmsPageId')->willReturn('cms-id');
        $cmsPageRepository->method('searchIds')->willReturn($this->idResult(null));
        $cmsPageRepository->expects($this->atLeastOnce())->method('create');
        $categories->expects($this->atLeastOnce())->method('assignLayout');

        $result = $seeder->seed($context, $report, $channels);

        $this->assertArrayHasKey('footerCategoryId', $result);
        $this->assertArrayHasKey('serviceCategoryId', $result);
    }

    public function testSeedNotesWhenNoBrowsableChannelsExist(): void
    {
        $categories = $this->createMock(CategoryWriter::class);
        $cmsPageRepository = $this->createMock(EntityRepository::class);
        $seeder = new FooterSeeder($categories, $cmsPageRepository, new DemoIdGenerator());

        $categories->method('upsert')->willReturn('category-id');
        $categories->method('defaultCmsPageId')->willReturn('cms-id');
        $cmsPageRepository->method('searchIds')->willReturn($this->idResult('existing-page'));

        $report = new SeedReport();
        $seeder->seed(Context::createDefaultContext(), $report, []);

        $this->assertStringContainsString('service menu was left empty', implode("\n", $report->notes()));
    }

    private function idResult(?string $firstId): IdSearchResult
    {
        $result = $this->createMock(IdSearchResult::class);
        $result->method('firstId')->willReturn($firstId);

        return $result;
    }
}
