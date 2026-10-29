<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Service;

use Kommandhub\DemoData\Exception\DemoDataException;
use Kommandhub\DemoData\Seeder\B2bSeeder;
use Kommandhub\DemoData\Seeder\CategorySeeder;
use Kommandhub\DemoData\Seeder\CrossSellingSeeder;
use Kommandhub\DemoData\Seeder\CustomerGroupSeeder;
use Kommandhub\DemoData\Seeder\CustomerSeeder;
use Kommandhub\DemoData\Seeder\FooterSeeder;
use Kommandhub\DemoData\Seeder\LandingPageSeeder;
use Kommandhub\DemoData\Seeder\ManufacturerSeeder;
use Kommandhub\DemoData\Seeder\MediaSeeder;
use Kommandhub\DemoData\Seeder\OrderSeeder;
use Kommandhub\DemoData\Seeder\PriceRuleSeeder;
use Kommandhub\DemoData\Seeder\ProductSeeder;
use Kommandhub\DemoData\Seeder\PropertySeeder;
use Kommandhub\DemoData\Seeder\SalesChannelSeeder;
use Kommandhub\DemoData\Seeder\ShopContextResolver;
use Kommandhub\DemoData\Seeder\TagSeeder;
use Kommandhub\DemoData\Seeder\TaxResolver;
use Kommandhub\DemoData\Service\DemoDataSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;

class DemoDataSeederTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function channels(): iterable
    {
        yield 'retail channel: no companies' => ['flagship', 0];
        yield 'trade channel: companies' => ['trade', 1];
    }

    #[DataProvider('channels')]
    public function testSeedWithMediaAndOrders(string $channelKey, int $b2bRuns): void
    {
        $b2bSeeder = $this->createMock(B2bSeeder::class);
        $b2bSeeder->expects($this->exactly($b2bRuns))->method('seed')
            ->with($this->anything(), $this->anything(), $this->anything(), 'group-id', 'salutation-id', 19.0);
        [$seeder, $manufacturerSeeder, $propertySeeder, $tagSeeder, $mediaSeeder, $salesChannelSeeder, $categorySeeder, $productSeeder, $footerSeeder, $crossSellingSeeder, $priceRuleSeeder, $landingPageSeeder, $customerGroupSeeder, $customerSeeder, $orderSeeder, $shop, $taxResolver] = $this->buildSeeder($b2bSeeder);
        $shop->method('salutationId')->willReturn('salutation-id');
        $context = Context::createDefaultContext();

        $manufacturerSeeder->method('seed')->willReturn(['m1' => 'man-id']);
        $propertySeeder->method('seed')->willReturn([]);
        $tagSeeder->method('seed')->willReturn([]);
        $mediaSeeder->method('seed')->willReturn([]);
        $mediaSeeder->method('seedRemote')->willReturn([]);
        $taxResolver->method('resolve')->willReturn(['id' => 'tax-id', 'rate' => 19.0]);
        $priceRuleSeeder->method('resolve')->willReturn('rule-id');
        $categorySeeder->method('seed')->willReturn([['id' => 'leaf-id', 'name' => 'Leaf', 'path' => ['Root'], 'ancestorIds' => ['root'], 'spec' => ['manufacturer' => 'm1']]]);
        $footerSeeder->method('seed')->willReturn(['footerCategoryId' => 'footer-cat-id', 'serviceCategoryId' => 'service-cat-id']);
        $shop->method('channel')->willReturn([
            'salesChannelId' => 'channel-id',
            'languageId' => 'lang-id',
            'currencyId' => 'curr-id',
            'countryId' => 'country-id',
            'paymentMethodId' => 'pay-id',
            'shippingMethodId' => 'ship-id',
            'customerGroupId' => 'group-id',
        ]);
        $salesChannelSeeder->method('resolveChannel')->willReturn(['id' => 'channel-id', 'rootCategoryId' => 'root-cat-id', 'rootOwned' => true]);
        $customerGroupSeeder->method('resolve')->willReturn('group-id');
        $customerSeeder->method('seed')->willReturn(['cust-id']);
        $productSeeder->method('seed')->willReturn([['leaf' => ['name' => 'Leaf'], 'productIds' => ['prod-id']]]);
        $orderSeeder->expects($this->once())->method('seed');
        $landingPageSeeder->expects($this->once())->method('seed');

        $report = $seeder->seed($context, [$channelKey], true, 1, true);

        $this->assertNotNull($report);
    }

    public function testSeedWithoutMediaOrOrdersSkipsThoseSubSeeders(): void
    {
        [$seeder, $manufacturerSeeder, $propertySeeder, $tagSeeder, $mediaSeeder, $salesChannelSeeder, $categorySeeder, $productSeeder, $footerSeeder, $crossSellingSeeder, $priceRuleSeeder, $landingPageSeeder, $customerGroupSeeder, $customerSeeder, $orderSeeder, $shop, $taxResolver] = $this->buildSeeder();
        $context = Context::createDefaultContext();

        $manufacturerSeeder->method('seed')->willReturn(['m1' => 'man-id']);
        $propertySeeder->method('seed')->willReturn([]);
        $tagSeeder->method('seed')->willReturn([]);
        $taxResolver->method('resolve')->willReturn(['id' => 'tax-id', 'rate' => 19.0]);
        $priceRuleSeeder->method('resolve')->willReturn('rule-id');
        $categorySeeder->method('seed')->willReturn([['id' => 'leaf-id', 'name' => 'Leaf', 'path' => ['Root'], 'ancestorIds' => ['root'], 'spec' => ['manufacturer' => 'm1']]]);
        $footerSeeder->method('seed')->willReturn(['footerCategoryId' => 'footer-cat-id', 'serviceCategoryId' => 'service-cat-id']);
        $shop->method('channel')->willReturn([
            'salesChannelId' => 'channel-id',
            'languageId' => 'lang-id',
            'currencyId' => 'curr-id',
            'countryId' => 'country-id',
            'paymentMethodId' => 'pay-id',
            'shippingMethodId' => 'ship-id',
            'customerGroupId' => 'group-id',
        ]);
        $salesChannelSeeder->method('resolveChannel')->willReturn(['id' => 'channel-id', 'rootCategoryId' => 'root-cat-id', 'rootOwned' => true]);
        $productSeeder->method('seed')->willReturn([]);

        $mediaSeeder->expects($this->never())->method('seed');
        $mediaSeeder->expects($this->never())->method('seedRemote');
        $orderSeeder->expects($this->never())->method('seed');

        $seeder->seed($context, ['flagship'], false, 1, false);
    }

    public function testSeedB2bSeedsOnlyTheCompaniesOnTheExistingTradeChannel(): void
    {
        $b2bSeeder = $this->createMock(B2bSeeder::class);
        $b2bSeeder->method('available')->willReturn(true);
        $b2bSeeder->expects($this->once())->method('seed')
            ->with($this->anything(), $this->anything(), ['salesChannelId' => 'trade-id', 'customerGroupId' => 'fallback-group'], 'trade-group', 'salutation-id', 7.5);
        [$seeder, $manufacturerSeeder, , , $mediaSeeder, $salesChannelSeeder, , $productSeeder, , , , , $customerGroupSeeder, , , $shop, $taxResolver] = $this->buildSeeder($b2bSeeder);
        $salesChannelSeeder->expects($this->once())->method('existingChannelId')->with($this->anything(), 'trade')->willReturn('trade-id');
        $salesChannelSeeder->expects($this->never())->method('resolveChannel');
        $shop->method('channel')->with($this->anything(), 'trade-id')->willReturn(['salesChannelId' => 'trade-id', 'customerGroupId' => 'fallback-group']);
        $shop->method('salutationId')->willReturn('salutation-id');
        $customerGroupSeeder->method('resolve')->with($this->anything(), $this->anything(), 'trade', 'fallback-group')->willReturn('trade-group');
        $taxResolver->method('resolve')->willReturn(['id' => 'tax-id', 'rate' => 7.5]);
        $manufacturerSeeder->expects($this->never())->method('seed');
        $mediaSeeder->expects($this->never())->method('seed');
        $productSeeder->expects($this->never())->method('seed');

        $seeder->seedB2b(Context::createDefaultContext());
    }

    public function testSeedB2bNeedsTheTradeChannel(): void
    {
        $b2bSeeder = $this->createMock(B2bSeeder::class);
        $b2bSeeder->method('available')->willReturn(true);
        $b2bSeeder->expects($this->never())->method('seed');
        [$seeder, , , , , $salesChannelSeeder] = $this->buildSeeder($b2bSeeder);
        $salesChannelSeeder->method('existingChannelId')->willReturn(null);

        $this->expectException(DemoDataException::class);
        $this->expectExceptionMessage('Generate the "Kommandhub Trade Supply" sales channel first');

        $seeder->seedB2b(Context::createDefaultContext());
    }

    public function testSeedB2bNeedsTheSuite(): void
    {
        $b2bSeeder = $this->createMock(B2bSeeder::class);
        $b2bSeeder->method('available')->willReturn(false);

        $this->expectException(DemoDataException::class);
        $this->expectExceptionMessage('B2B Foundation is not active');

        $this->buildSeeder($b2bSeeder)[0]->seedB2b(Context::createDefaultContext());
    }

    public function testSeedB2bWithoutASeederRefusesToo(): void
    {
        $this->expectException(DemoDataException::class);

        $this->buildSeeder()[0]->seedB2b(Context::createDefaultContext());
    }

    public function testB2bStatusCombinesTheSuiteAndTheTradeChannel(): void
    {
        $b2bSeeder = $this->createMock(B2bSeeder::class);
        $b2bSeeder->method('describe')->willReturn(['available' => true, 'lists' => true, 'companies' => [['name' => 'N']]]);
        [$seeder, , , , , $salesChannelSeeder] = $this->buildSeeder($b2bSeeder);
        $salesChannelSeeder->method('existingChannelId')->willReturn('trade-id');

        $state = $seeder->b2bStatus(Context::createDefaultContext());

        static::assertTrue($state['available']);
        static::assertTrue($state['channelReady']);
        static::assertSame('demo12345', $state['password']);
        static::assertSame([['name' => 'N']], $state['companies']);
    }

    public function testB2bStatusWithoutASeederReportsNothingAvailable(): void
    {
        [$seeder, , , , , $salesChannelSeeder] = $this->buildSeeder();
        $salesChannelSeeder->method('existingChannelId')->willReturn(null);

        $state = $seeder->b2bStatus(Context::createDefaultContext());

        static::assertFalse($state['available']);
        static::assertFalse($state['channelReady']);
        static::assertSame([], $state['companies']);
    }

    /** @return array{0: DemoDataSeeder,1: ManufacturerSeeder,2: PropertySeeder,3: TagSeeder,4: MediaSeeder,5: SalesChannelSeeder,6: CategorySeeder,7: ProductSeeder,8: FooterSeeder,9: CrossSellingSeeder,10: PriceRuleSeeder,11: LandingPageSeeder,12: CustomerGroupSeeder,13: CustomerSeeder,14: OrderSeeder,15: ShopContextResolver,16: TaxResolver} */
    private function buildSeeder(?B2bSeeder $b2bSeeder = null): array
    {
        $manufacturerSeeder = $this->createMock(ManufacturerSeeder::class);
        $propertySeeder = $this->createMock(PropertySeeder::class);
        $tagSeeder = $this->createMock(TagSeeder::class);
        $mediaSeeder = $this->createMock(MediaSeeder::class);
        $salesChannelSeeder = $this->createMock(SalesChannelSeeder::class);
        $categorySeeder = $this->createMock(CategorySeeder::class);
        $productSeeder = $this->createMock(ProductSeeder::class);
        $footerSeeder = $this->createMock(FooterSeeder::class);
        $crossSellingSeeder = $this->createMock(CrossSellingSeeder::class);
        $priceRuleSeeder = $this->createMock(PriceRuleSeeder::class);
        $landingPageSeeder = $this->createMock(LandingPageSeeder::class);
        $customerGroupSeeder = $this->createMock(CustomerGroupSeeder::class);
        $customerSeeder = $this->createMock(CustomerSeeder::class);
        $orderSeeder = $this->createMock(OrderSeeder::class);
        $shop = $this->createMock(ShopContextResolver::class);
        $taxResolver = $this->createMock(TaxResolver::class);
        $logger = $this->createMock(LoggerInterface::class);

        $seeder = new DemoDataSeeder(
            $manufacturerSeeder,
            $propertySeeder,
            $tagSeeder,
            $mediaSeeder,
            $salesChannelSeeder,
            $categorySeeder,
            $productSeeder,
            $footerSeeder,
            $crossSellingSeeder,
            $priceRuleSeeder,
            $landingPageSeeder,
            $customerGroupSeeder,
            $customerSeeder,
            $orderSeeder,
            $shop,
            $taxResolver,
            $logger,
            $b2bSeeder
        );

        return [$seeder, $manufacturerSeeder, $propertySeeder, $tagSeeder, $mediaSeeder, $salesChannelSeeder, $categorySeeder, $productSeeder, $footerSeeder, $crossSellingSeeder, $priceRuleSeeder, $landingPageSeeder, $customerGroupSeeder, $customerSeeder, $orderSeeder, $shop, $taxResolver];
    }
}
