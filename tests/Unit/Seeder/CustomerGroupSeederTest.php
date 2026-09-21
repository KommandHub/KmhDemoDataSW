<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Seeder;

use Kommandhub\DemoData\Seeder\CustomerGroupSeeder;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\EntityResolver;
use Kommandhub\DemoData\Service\ResolvedEntity;
use Kommandhub\DemoData\Service\SeedReport;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class CustomerGroupSeederTest extends TestCase
{
    public function testResolveTradeCreatesMissingGroup(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $resolver = $this->createMock(EntityResolver::class);
        $ids = new DemoIdGenerator();
        $resolver->method('ids')->willReturn($ids);

        $seeder = new CustomerGroupSeeder($repository, $resolver);
        $context = Context::createDefaultContext();
        $report = new SeedReport();

        $groupId = $ids->id('customer-group', 'trade');
        $resolver->method('resolveByField')->willReturn(ResolvedEntity::missing($groupId));
        $repository->expects($this->once())->method('create');

        $this->assertSame($groupId, $seeder->resolve($context, $report, 'trade', 'fallback-id'));
    }

    public function testResolveTradeReusesExistingGroups(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $resolver = $this->createMock(EntityResolver::class);
        $ids = new DemoIdGenerator();
        $resolver->method('ids')->willReturn($ids);
        $seeder = new CustomerGroupSeeder($repository, $resolver);

        $resolver->method('resolveByField')->willReturnOnConsecutiveCalls(
            ResolvedEntity::owned('owned-group', null),
            ResolvedEntity::adopted('adopted-group', null)
        );

        $this->assertSame('owned-group', $seeder->resolve(Context::createDefaultContext(), new SeedReport(), 'trade', 'fallback-id'));
        $this->assertSame('adopted-group', $seeder->resolve(Context::createDefaultContext(), new SeedReport(), 'trade', 'fallback-id'));
    }

    public function testResolveFallbackForNonTradeChannels(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $resolver = $this->createMock(EntityResolver::class);

        $seeder = new CustomerGroupSeeder($repository, $resolver);

        $this->assertSame('fallback-id', $seeder->resolve(Context::createDefaultContext(), new SeedReport(), 'flagship', 'fallback-id'));
    }
}
