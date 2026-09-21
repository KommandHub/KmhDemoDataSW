<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Seeder;

use Kommandhub\DemoData\Exception\DemoDataException;
use Kommandhub\DemoData\Seeder\TaxResolver;
use Kommandhub\DemoData\Service\SeedReport;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxEntity;

class TaxResolverTest extends TestCase
{
    private EntityRepository $repository;
    private TaxResolver $resolver;
    private Context $context;
    private SeedReport $report;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->resolver = new TaxResolver($this->repository);
        $this->context = Context::createDefaultContext();
        $this->report = new SeedReport();
    }

    public function testResolveExactMatch(): void
    {
        $tax = new TaxEntity();
        $tax->setId('tax-id');
        $tax->setTaxRate(19.0);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new TaxCollection([$tax]));
        $this->repository->method('search')->willReturn($searchResult);

        $result = $this->resolver->resolve($this->context, $this->report, 19.0);

        $this->assertEquals('tax-id', $result['id']);
        $this->assertEquals(19.0, $result['rate']);
    }

    public function testResolveFallback(): void
    {
        $tax = new TaxEntity();
        $tax->setId('fallback-id');
        $tax->setTaxRate(20.0);

        $emptyResult = $this->createMock(EntitySearchResult::class);
        $emptyResult->method('getEntities')->willReturn(new TaxCollection([]));

        $fallbackResult = $this->createMock(EntitySearchResult::class);
        $fallbackResult->method('getEntities')->willReturn(new TaxCollection([$tax]));

        $this->repository->method('search')->willReturnOnConsecutiveCalls($emptyResult, $fallbackResult);

        $result = $this->resolver->resolve($this->context, $this->report, 19.0);

        $this->assertEquals('fallback-id', $result['id']);
        $this->assertEquals(20.0, $result['rate']);
    }

    public function testResolveNoTaxFound(): void
    {
        $emptyResult = $this->createMock(EntitySearchResult::class);
        $emptyResult->method('getEntities')->willReturn(new TaxCollection([]));
        $this->repository->method('search')->willReturn($emptyResult);

        $this->expectException(DemoDataException::class);
        $this->expectExceptionMessage('No tax rate configured.');

        $this->resolver->resolve($this->context, $this->report, 19.0);
    }
}
