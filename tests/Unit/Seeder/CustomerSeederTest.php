<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Seeder;

use Kommandhub\DemoData\Seeder\CustomerSeeder;
use Kommandhub\DemoData\Service\BatchWriter;
use Kommandhub\DemoData\Service\CustomerPayloadBuilder;
use Kommandhub\DemoData\Service\EntityResolver;
use Kommandhub\DemoData\Service\SeedReport;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class CustomerSeederTest extends TestCase
{
    public function testSeedCreatesOnlyMissingCustomers(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $payloadBuilder = $this->createMock(CustomerPayloadBuilder::class);
        $resolver = $this->createMock(EntityResolver::class);

        $seeder = new CustomerSeeder($repository, $payloadBuilder, new BatchWriter(), $resolver);
        $context = Context::createDefaultContext();
        $report = new SeedReport();

        $payloadBuilder->method('build')->willReturnCallback(static function ($key, $index) {
            return ['id' => 'cust-' . $index, 'email' => 'test-' . $index . '@example.com', 'addresses' => [['id' => 'addr-' . $index]]];
        });

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('getIds')->willReturn(['cust-1']);
        $repository->method('searchIds')->willReturn($idSearchResult);
        $repository->expects($this->once())->method('create')->with($this->callback(static fn (array $batch): bool => $batch[0]['id'] === 'cust-2'));

        $ids = $seeder->seed($context, $report, 'flagship', [
            'salesChannelId' => 'sc-id',
            'languageId' => 'l-id',
            'countryId' => 'c-id',
            'paymentMethodId' => 'p-id',
        ], 'cg-id', 's-id', 2);

        $this->assertSame(['cust-1', 'cust-2'], $ids);
    }

    public function testLoginEmailsAndResolverHelpers(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $payloadBuilder = $this->createMock(CustomerPayloadBuilder::class);
        $resolver = $this->createMock(EntityResolver::class);
        $payloadBuilder->method('loginEmail')->willReturnCallback(static fn (string $channelKey, int $index): string => $channelKey . '-' . $index . '@example.com');

        $seeder = new CustomerSeeder($repository, $payloadBuilder, new BatchWriter(), $resolver);

        $emails = $seeder->loginEmails('flagship');

        $this->assertNotEmpty($emails);
        $this->assertSame('flagship-1@example.com', $emails[0]);
        $this->assertSame($resolver, $seeder->resolver());
    }
}
