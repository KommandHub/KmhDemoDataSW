<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Seeder;

use Kommandhub\DemoData\Seeder\CategorySeeder;
use Kommandhub\DemoData\Seeder\CategoryWriter;
use Kommandhub\DemoData\Service\SeedReport;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

class CategorySeederTest extends TestCase
{
    public function testSeedBuildsLeavesAndMapsPhotoMediaIds(): void
    {
        $writer = $this->createMock(CategoryWriter::class);
        $seeder = new CategorySeeder($writer);
        $context = Context::createDefaultContext();
        $report = new SeedReport();

        $writer->method('defaultCmsPageId')->willReturn('cms-page-id');
        $writer->method('upsert')->willReturnOnConsecutiveCalls('parent-id', 'child-id');

        $tree = [[
            'name' => 'Parent',
            'children' => [[
                'name' => 'Child',
                'photos' => ['photo-1'],
            ]],
        ]];

        $leaves = $seeder->seed($context, $report, 'flagship', 'root-id', $tree, [], ['photo-1' => 'media-1']);

        $this->assertCount(1, $leaves);
        $this->assertSame('Child', $leaves[0]['name']);
        $this->assertSame(['media-1'], $leaves[0]['spec']['photoMediaIds']);
    }

    public function testFieldsIncludeOptionalKeysWhenValuesArePresent(): void
    {
        $writer = $this->createMock(CategoryWriter::class);
        $seeder = new CategorySeeder($writer);
        $method = (new \ReflectionClass($seeder))->getMethod('fields');

        $fields = $method->invoke($seeder, 'Shoes', 'cms-id', 'media-id', 'after-id');

        $this->assertSame('media-id', $fields['mediaId']);
        $this->assertSame('cms-id', $fields['cmsPageId']);
        $this->assertSame('after-id', $fields['afterCategoryId']);
    }

    public function testFieldsOmitOptionalKeysWhenValuesAreNull(): void
    {
        $writer = $this->createMock(CategoryWriter::class);
        $seeder = new CategorySeeder($writer);
        $method = (new \ReflectionClass($seeder))->getMethod('fields');

        $fields = $method->invoke($seeder, 'Shoes', null, null, null);

        $this->assertArrayNotHasKey('mediaId', $fields);
        $this->assertArrayNotHasKey('cmsPageId', $fields);
        $this->assertArrayNotHasKey('afterCategoryId', $fields);
    }
}
