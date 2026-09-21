<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Seeder;

use Kommandhub\DemoData\Blueprint\DemoBlueprint;
use Kommandhub\DemoData\Seeder\TagSeeder;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\EntityResolver;
use Kommandhub\DemoData\Service\ResolvedEntity;
use Kommandhub\DemoData\Service\SeedReport;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class TagSeederTest extends TestCase
{
    public function testSeed(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $resolver = $this->createMock(EntityResolver::class);
        $ids = new DemoIdGenerator();
        $resolver->method('ids')->willReturn($ids);

        $seeder = new TagSeeder($repository, $resolver);
        $context = Context::createDefaultContext();
        $report = new SeedReport();

        $tagId = $ids->id('tag', DemoBlueprint::tags()[0]);
        $resolver->method('resolveByField')->willReturnCallback(function ($repo, $context, $id, $field, $value) {
            if ($value === DemoBlueprint::tags()[0]) {
                return ResolvedEntity::missing($id);
            }

            return ResolvedEntity::owned($id, null);
        });

        $repository->expects($this->once())->method('create');

        $result = $seeder->seed($context, $report);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertContains($tagId, array_values($result));
    }
}
