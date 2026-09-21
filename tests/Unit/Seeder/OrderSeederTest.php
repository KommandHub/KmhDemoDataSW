<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Seeder;

use Kommandhub\DemoData\Seeder\OrderSeeder;
use Kommandhub\DemoData\Seeder\ShopContextResolver;
use Kommandhub\DemoData\Service\BatchWriter;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\DeterministicValueGenerator;
use Kommandhub\DemoData\Service\OrderPayloadBuilder;
use Kommandhub\DemoData\Service\SeedReport;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class OrderSeederTest extends TestCase
{
    public function testSeedCreatesMissingOrders(): void
    {
        [$orderRepository, $productRepository, $customerRepository, $payloadBuilder, $shop, $seeder, $context] = $this->seeder();
        $report = new SeedReport();

        $shop->method('stateId')->willReturn('state-id');
        $productRepository->method('search')->willReturn($this->productResult($context, [$this->product($context, 'p1', 'PN1', 'Product 1', 11.9)]));
        $customerRepository->method('search')->willReturn($this->customerResult([$this->customer('c1', true)]));
        $payloadBuilder->method('build')->willReturn(['id' => 'order-id', 'lineItems' => [['id' => 'line-1']]]);

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('getIds')->willReturn([]);
        $orderRepository->method('searchIds')->willReturn($idSearchResult);
        $orderRepository->expects($this->once())->method('create')->with(
            $this->callback(static fn (array $batch): bool => $batch[0]['id'] === 'order-id'),
            $this->identicalTo($context)
        );

        $seeder->seed($context, $report, 'flagship', $this->channelContext(), ['p1'], ['c1'], 19.0, 1);
    }

    public function testSeedSkipsWhenNoProductsOrCustomersAreAvailable(): void
    {
        [, , , , , $seeder, $context] = $this->seeder();
        $report = new SeedReport();

        $seeder->seed($context, $report, 'flagship', $this->channelContext(), [], ['c1'], 19.0, 2);
        $seeder->seed($context, $report, 'flagship', $this->channelContext(), ['p1'], [], 19.0, 2);

        $rows = $report->toTable();
        $this->assertNotEmpty($rows);
        $this->assertStringContainsString('4', implode('|', $rows[0]));
    }

    public function testSeedSkipsWhenPayloadBuilderReturnsNullAndWhenOrderAlreadyExists(): void
    {
        [$orderRepository, $productRepository, $customerRepository, $payloadBuilder, $shop, $seeder, $context] = $this->seeder();
        $shop->method('stateId')->willReturn('state-id');
        $productRepository->method('search')->willReturn($this->productResult($context, [$this->product($context, 'p1', 'PN1', 'Product 1', 11.9)]));
        $customerRepository->method('search')->willReturn($this->customerResult([$this->customer('c1', true)]));
        $payloadBuilder->method('build')->willReturnOnConsecutiveCalls(null, ['id' => 'order-id']);

        $known = $this->createMock(IdSearchResult::class);
        $known->method('getIds')->willReturn(['order-id']);
        $orderRepository->method('searchIds')->willReturn($known);
        $orderRepository->expects($this->never())->method('create');

        $seeder->seed($context, new SeedReport(), 'flagship', $this->channelContext(), ['p1'], ['c1'], 19.0, 2);
    }

    public function testSeedSkipsWhenLoadedCatalogueOrCustomersAreEmpty(): void
    {
        [$orderRepository, $productRepository, $customerRepository, $payloadBuilder, $shop, $seeder, $context] = $this->seeder();
        $shop->method('stateId')->willReturn('state-id');
        $productRepository->method('search')->willReturn($this->productResult($context, []));
        $customerRepository->method('search')->willReturn($this->customerResult([$this->customer('c1', true)]));
        $orderRepository->expects($this->never())->method('create');

        $seeder->seed($context, new SeedReport(), 'flagship', $this->channelContext(), ['p1'], ['c1'], 19.0, 1);
    }

    public function testSeedReturnsWithoutWritingWhenEveryBuiltPayloadIsNull(): void
    {
        [$orderRepository, $productRepository, $customerRepository, $payloadBuilder, $shop, $seeder, $context] = $this->seeder();
        $shop->method('stateId')->willReturn('state-id');
        $productRepository->method('search')->willReturn($this->productResult($context, [$this->product($context, 'p1', 'PN1', 'Product 1', 11.9)]));
        $customerRepository->method('search')->willReturn($this->customerResult([$this->customer('c1', true)]));
        $payloadBuilder->method('build')->willReturn(null);
        $orderRepository->expects($this->never())->method('searchIds');
        $orderRepository->expects($this->never())->method('create');

        $seeder->seed($context, new SeedReport(), 'flagship', $this->channelContext(), ['p1'], ['c1'], 19.0, 1);
    }

    public function testCatalogueSkipsIncompleteProducts(): void
    {
        [, $productRepository, , , , $seeder, $context] = $this->seeder();
        $productRepository->method('search')->willReturn($this->productResult($context, [
            $this->product($context, 'good', 'PN1', 'Product 1', 11.9),
            $this->product($context, 'bad', 'PN2', null, 11.9),
        ]));

        $method = (new \ReflectionClass($seeder))->getMethod('catalogue');
        $catalogue = $method->invoke($seeder, $context, 'flagship', ['good', 'bad']);

        $this->assertCount(1, $catalogue);
        $this->assertSame('good', $catalogue[0]['id']);
    }

    public function testCustomersSkipsIncompleteCustomerRows(): void
    {
        [, , $customerRepository, , , $seeder, $context] = $this->seeder();
        $customerRepository->method('search')->willReturn($this->customerResult([
            $this->customer('good', true),
            $this->customer('bad', false),
        ]));

        $method = (new \ReflectionClass($seeder))->getMethod('customers');
        $customers = $method->invoke($seeder, $context, ['good', 'bad']);

        $this->assertCount(1, $customers);
        $this->assertSame('good', $customers[0]['id']);
    }

    public function testStateIdsResolveAConfiguredStateTriplet(): void
    {
        [, , , , $shop, $seeder, $context] = $this->seeder();
        $shop->method('stateId')->willReturnCallback(static fn (Context $context, string $machine, string $name): string => $machine . ':' . $name);

        $method = (new \ReflectionClass($seeder))->getMethod('stateIds');
        $states = $method->invoke($seeder, $context, 'flagship|1');

        $this->assertArrayHasKey('order', $states);
        $this->assertArrayHasKey('transaction', $states);
        $this->assertArrayHasKey('delivery', $states);
    }

    /** @return array{0: EntityRepository,1: EntityRepository,2: EntityRepository,3: OrderPayloadBuilder,4: ShopContextResolver,5: OrderSeeder,6: Context} */
    private function seeder(): array
    {
        $orderRepository = $this->createMock(EntityRepository::class);
        $productRepository = $this->createMock(EntityRepository::class);
        $customerRepository = $this->createMock(EntityRepository::class);
        $payloadBuilder = $this->createMock(OrderPayloadBuilder::class);
        $shop = $this->createMock(ShopContextResolver::class);
        $seeder = new OrderSeeder(
            $orderRepository,
            $productRepository,
            $customerRepository,
            $payloadBuilder,
            new BatchWriter(),
            $shop,
            new DeterministicValueGenerator(),
            new DemoIdGenerator()
        );

        return [$orderRepository, $productRepository, $customerRepository, $payloadBuilder, $shop, $seeder, Context::createDefaultContext()];
    }

    /** @return array{salesChannelId: string, languageId: string, currencyId: string, countryId: string, paymentMethodId: string, shippingMethodId: string} */
    private function channelContext(): array
    {
        return [
            'salesChannelId' => 'sc-id',
            'languageId' => 'l-id',
            'currencyId' => 'cu-id',
            'countryId' => 'co-id',
            'paymentMethodId' => 'pm-id',
            'shippingMethodId' => 'sm-id',
        ];
    }

    private function product(Context $context, string $id, string $number, ?string $name, float $gross): ProductEntity
    {
        $product = new ProductEntity();
        $product->setId($id);
        $product->setUniqueIdentifier($id);
        $product->setProductNumber($number);

        if ($name !== null) {
            $product->setTranslated(['name' => $name]);
        }
        $product->setPrice(new PriceCollection([new Price($context->getCurrencyId(), 10.0, $gross, false)]));

        return $product;
    }

    private function customer(string $id, bool $complete): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId($id);
        $customer->setUniqueIdentifier($id);
        $customer->setCustomerNumber('CN-' . $id);
        $customer->setFirstName('F');
        $customer->setLastName('L');
        $customer->setEmail($id . '@example.com');

        if ($complete) {
            $customer->setSalutationId('salutation-id');
            $address = new CustomerAddressEntity();
            $address->setStreet('S');
            $address->setZipcode('Z');
            $address->setCity('C');
            $customer->setDefaultBillingAddress($address);
        }

        return $customer;
    }

    private function productResult(Context $context, array $products): EntitySearchResult
    {
        return new EntitySearchResult('product', count($products), new ProductCollection($products), null, new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria(), $context);
    }

    private function customerResult(array $customers): EntitySearchResult
    {
        $context = Context::createDefaultContext();

        return new EntitySearchResult('customer', count($customers), new CustomerCollection($customers), null, new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria(), $context);
    }
}
