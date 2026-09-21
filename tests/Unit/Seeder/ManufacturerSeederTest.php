<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Seeder;

use Kommandhub\DemoData\Seeder\ManufacturerSeeder;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\EntityResolver;
use Kommandhub\DemoData\Service\ResolvedEntity;
use Kommandhub\DemoData\Service\SeedReport;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class ManufacturerSeederTest extends TestCase
{
    public function testSeedCreatesAndUpdatesManufacturers(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $resolver = $this->createMock(EntityResolver::class);
        $ids = new DemoIdGenerator();
        $resolver->method('ids')->willReturn($ids);

        $seeder = new ManufacturerSeeder($repository, $resolver);
        $resolver->method('resolveByField')->willReturnCallback(function ($repo, $context, $id, $field, $value) {
            return match ($value) {
                'Aurora Audio' => ResolvedEntity::missing($id),
                'Meridian Mobile' => ResolvedEntity::adopted($id, null),
                default => ResolvedEntity::owned($id, null),
            };
        });
        $resolver->method('enrichmentPayload')->willReturn(['id' => 'northstar', 'description' => 'Top-up']);

        $repository->expects($this->once())->method('create');
        $repository->expects($this->once())->method('update');

        $result = $seeder->seed(Context::createDefaultContext(), new SeedReport());

        $this->assertArrayHasKey('aurora-audio', $result);
        $this->assertArrayHasKey('meridian-mobile', $result);
    }
}
