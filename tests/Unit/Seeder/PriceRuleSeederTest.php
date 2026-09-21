<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Seeder;

use Kommandhub\DemoData\Seeder\PriceRuleSeeder;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\EntityResolver;
use Kommandhub\DemoData\Service\ResolvedEntity;
use Kommandhub\DemoData\Service\SeedReport;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class PriceRuleSeederTest extends TestCase
{
    public function testResolveNew(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $resolver = $this->createMock(EntityResolver::class);
        $ids = new DemoIdGenerator();
        $resolver->method('ids')->willReturn($ids);

        $seeder = new PriceRuleSeeder($repository, $resolver);
        $context = Context::createDefaultContext();
        $report = new SeedReport();

        $ruleId = $ids->id('price-rule', 'flagship');
        $resolver->method('resolveByField')->willReturn(ResolvedEntity::missing($ruleId));
        $repository->expects($this->once())->method('create');

        $this->assertSame($ruleId, $seeder->resolve($context, $report, 'flagship', 'Flagship', 'sc-id'));
    }

    public function testResolveReusesExistingRules(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $resolver = $this->createMock(EntityResolver::class);
        $resolver->method('ids')->willReturn(new DemoIdGenerator());
        $seeder = new PriceRuleSeeder($repository, $resolver);

        $resolver->method('resolveByField')->willReturnOnConsecutiveCalls(
            ResolvedEntity::owned('owned-rule', null),
            ResolvedEntity::adopted('adopted-rule', null)
        );

        $this->assertSame('owned-rule', $seeder->resolve(Context::createDefaultContext(), new SeedReport(), 'flagship', 'Flagship', 'sc-id'));
        $this->assertSame('adopted-rule', $seeder->resolve(Context::createDefaultContext(), new SeedReport(), 'flagship', 'Flagship', 'sc-id'));
    }
}
