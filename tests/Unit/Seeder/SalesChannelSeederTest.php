<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Seeder;

use Kommandhub\DemoData\Exception\DemoDataException;
use Kommandhub\DemoData\Seeder\CategoryWriter;
use Kommandhub\DemoData\Seeder\SalesChannelSeeder;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\EntityResolver;
use Kommandhub\DemoData\Service\ResolvedEntity;
use Kommandhub\DemoData\Service\SeedReport;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;

class SalesChannelSeederTest extends TestCase
{
    private EntityRepository&MockObject $salesChannelRepository;
    private EntityRepository&MockObject $salesChannelDomainRepository;
    private CategoryWriter&MockObject $categories;
    private EntityResolver&MockObject $resolver;
    private SalesChannelSeeder $seeder;
    private Context $context;
    private SeedReport $report;
    private DemoIdGenerator $ids;

    protected function setUp(): void
    {
        $this->salesChannelRepository = $this->createMock(EntityRepository::class);
        $this->salesChannelDomainRepository = $this->createMock(EntityRepository::class);
        $this->categories = $this->createMock(CategoryWriter::class);
        $this->resolver = $this->createMock(EntityResolver::class);
        $this->seeder = new SalesChannelSeeder(
            $this->salesChannelRepository,
            $this->salesChannelDomainRepository,
            $this->categories,
            $this->resolver
        );
        $this->context = Context::createDefaultContext();
        $this->report = new SeedReport();
        $this->ids = new DemoIdGenerator();
        $this->resolver->method('ids')->willReturn($this->ids);
    }

    public function testResolveChannelReusesOwnedChannelWithOwnedRoot(): void
    {
        $channel = new SalesChannelEntity();
        $channel->setUniqueIdentifier('channel-id');
        $channel->setNavigationCategoryId($this->ids->id('category', 'flagship'));

        $this->resolver->method('resolve')->willReturn(ResolvedEntity::owned('channel-id', $channel));
        $this->categories->expects($this->never())->method('fillLayoutIfEmpty');

        $result = $this->seeder->resolveChannel($this->context, $this->report, 'flagship', $this->definition());

        $this->assertSame('channel-id', $result['id']);
        $this->assertSame($this->ids->id('category', 'flagship'), $result['rootCategoryId']);
        $this->assertTrue($result['rootOwned']);
    }

    public function testResolveChannelAdoptedMerchantRootGetsLayoutFilled(): void
    {
        $channel = new SalesChannelEntity();
        $channel->setUniqueIdentifier('adopted-id');
        $channel->setName('Storefront');
        $channel->setNavigationCategoryId('merchant-root');

        $this->resolver->method('resolve')->willReturn(ResolvedEntity::adopted('adopted-id', $channel));
        $this->categories->expects($this->once())
            ->method('fillLayoutIfEmpty')
            ->with($this->context, $this->report, 'root_category', 'merchant-root', 'landingpage');

        $result = $this->seeder->resolveChannel($this->context, $this->report, 'flagship', $this->definition());

        $this->assertSame('adopted-id', $result['id']);
        $this->assertFalse($result['rootOwned']);
    }

    public function testResolveChannelRepairsMissingNavigationCategory(): void
    {
        $this->resolver->method('resolve')->willReturn(ResolvedEntity::owned('channel-id', null));
        $this->categories->expects($this->once())->method('upsert')->willReturn('new-root-id');
        $this->salesChannelRepository->expects($this->once())
            ->method('update')
            ->with([['id' => 'channel-id', 'navigationCategoryId' => 'new-root-id']], $this->context);

        $result = $this->seeder->resolveChannel($this->context, $this->report, 'flagship', $this->definition());

        $this->assertSame('new-root-id', $result['rootCategoryId']);
        $this->assertTrue($result['rootOwned']);
    }

    public function testResolveChannelCreatesMissingChannel(): void
    {
        $this->resolver->method('resolve')->willReturn(ResolvedEntity::missing($this->ids->id('sales-channel', 'flagship')));
        $this->categories->expects($this->once())->method('upsert')->willReturn('root-id');

        $template = $this->templateChannel(withAssociations: true, snippetSetId: 'snippet-id');
        $searchResult = $this->entitySearchResult(new SalesChannelCollection([$template]));
        $this->salesChannelRepository->method('search')->willReturn($searchResult);

        $freeDomain = $this->createMock(IdSearchResult::class);
        $freeDomain->method('getTotal')->willReturn(0);
        $this->salesChannelDomainRepository->method('searchIds')->willReturn($freeDomain);

        $this->salesChannelRepository->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $payloads): bool {
                $payload = $payloads[0];

                return isset($payload['domains'][0]['url'])
                    && $payload['domains'][0]['url'] === 'https://example.com'
                    && $payload['navigationCategoryId'] === 'root-id';
            }), $this->context);

        $result = $this->seeder->resolveChannel($this->context, $this->report, 'flagship', $this->definition());

        $this->assertTrue($result['created']);
        $this->assertSame('root-id', $result['rootCategoryId']);
    }

    public function testBrowsableChannelsSkipsNonHttpDomainsAndFallsBackToStoreName(): void
    {
        $hidden = new SalesChannelEntity();
        $hidden->setId('hidden');
        $hidden->setUniqueIdentifier('hidden');
        $hiddenDomain = new SalesChannelDomainEntity();
        $hiddenDomain->setId('domain-1');
        $hiddenDomain->setUniqueIdentifier('domain-1');
        $hiddenDomain->setUrl('api://headless');
        $hidden->setDomains(new SalesChannelDomainCollection([$hiddenDomain]));

        $visible = new SalesChannelEntity();
        $visible->setId('visible');
        $visible->setUniqueIdentifier('visible');
        $visibleDomain = new SalesChannelDomainEntity();
        $visibleDomain->setId('domain-2');
        $visibleDomain->setUniqueIdentifier('domain-2');
        $visibleDomain->setUrl('https://store.example');
        $visible->setDomains(new SalesChannelDomainCollection([$visibleDomain]));

        $this->salesChannelRepository->method('search')
            ->willReturn($this->entitySearchResult(new SalesChannelCollection([$hidden, $visible])));

        $result = $this->seeder->browsableChannels($this->context);

        $this->assertSame([['id' => 'visible', 'name' => 'Store', 'url' => 'https://store.example']], $result);
    }

    public function testAssignFooterAndServiceOnlyUpdatesMissingAssignments(): void
    {
        $needsUpdate = new SalesChannelEntity();
        $needsUpdate->setId('sc1');
        $needsUpdate->setUniqueIdentifier('sc1');

        $alreadyConfigured = new SalesChannelEntity();
        $alreadyConfigured->setId('sc2');
        $alreadyConfigured->setUniqueIdentifier('sc2');
        $alreadyConfigured->setFooterCategoryId('footer-existing');
        $alreadyConfigured->setServiceCategoryId('service-existing');

        $this->salesChannelRepository->method('search')
            ->willReturn($this->entitySearchResult(new SalesChannelCollection([$needsUpdate, $alreadyConfigured])));

        $this->salesChannelRepository->expects($this->once())
            ->method('update')
            ->with([['id' => 'sc1', 'footerCategoryId' => 'footer-id', 'serviceCategoryId' => 'service-id']], $this->context);

        $this->seeder->assignFooterAndService($this->context, $this->report, 'footer-id', 'service-id');
    }

    public function testAssignFooterAndServiceDoesNothingWhenEverythingExists(): void
    {
        $channel = new SalesChannelEntity();
        $channel->setId('sc1');
        $channel->setUniqueIdentifier('sc1');
        $channel->setFooterCategoryId('footer-id');
        $channel->setServiceCategoryId('service-id');

        $this->salesChannelRepository->method('search')
            ->willReturn($this->entitySearchResult(new SalesChannelCollection([$channel])));
        $this->salesChannelRepository->expects($this->never())->method('update');

        $this->seeder->assignFooterAndService($this->context, $this->report, 'footer-id', 'service-id');
    }

    public function testCreateRootCategory(): void
    {
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('createRootCategory');
        $this->categories->expects($this->once())->method('upsert')->willReturn('new-root-id');

        $result = $method->invoke($this->seeder, $this->context, $this->report, 'flagship', 'Flagship');

        $this->assertSame('new-root-id', $result);
    }

    public function testCreateChannelWithoutUsableDomainAddsNoteInstead(): void
    {
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('createChannel');

        $template = $this->templateChannel(withAssociations: false, snippetSetId: null);
        $this->salesChannelRepository->method('search')
            ->willReturn($this->entitySearchResult(new SalesChannelCollection([$template])));
        $this->salesChannelRepository->expects($this->once())
            ->method('create')
            ->with($this->callback(static fn (array $payloads): bool => !isset($payloads[0]['domains'])));

        $method->invoke($this->seeder, $this->context, $this->report, 'sc-id', $this->definition(), 'root-id');

        $this->assertStringContainsString('created without a domain', implode("\n", $this->report->notes()));
    }

    public function testFetchTemplateChannelReturnsDocumentedKeysAndFallbackLists(): void
    {
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('fetchTemplateChannel');

        $template = $this->templateChannel(withAssociations: false, snippetSetId: null);
        $this->salesChannelRepository->method('search')
            ->willReturn($this->entitySearchResult(new SalesChannelCollection([$template])));

        $result = $method->invoke($this->seeder, $this->context);

        $this->assertSame('l-id', $result['languageId']);
        $this->assertSame('c-id', $result['currencyId']);
        $this->assertSame(['l-id'], $result['languages']);
        $this->assertSame(['c-id'], $result['currencies']);
        $this->assertNull($result['snippetSetId']);
    }

    public function testFetchTemplateChannelThrowsWhenNoTemplateExists(): void
    {
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('fetchTemplateChannel');

        $this->salesChannelRepository->method('search')
            ->willReturn($this->entitySearchResult(new SalesChannelCollection([])));

        $this->expectException(DemoDataException::class);
        $method->invoke($this->seeder, $this->context);
    }

    public function testDomainTakenTrimsTrailingSlash(): void
    {
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('domainTaken');

        $searchResult = $this->createMock(IdSearchResult::class);
        $searchResult->method('getTotal')->willReturn(1);
        $this->salesChannelDomainRepository->method('searchIds')->willReturn($searchResult);

        $this->assertTrue($method->invoke($this->seeder, $this->context, 'https://taken.example/'));
    }

    public function testToIdListDeduplicatesWhileKeepingOrder(): void
    {
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('toIdList');

        $this->assertSame(
            [['id' => 'a'], ['id' => 'b']],
            $method->invoke($this->seeder, ['a', 'b', 'a'])
        );
    }

    /**
     * @return array{name: string, rootCategory: string, domain: string, adopt: array<int, string>}
     */
    private function definition(): array
    {
        return [
            'name' => 'Flagship',
            'rootCategory' => 'Flagship Root',
            'domain' => 'https://example.com',
            'adopt' => ['Storefront'],
        ];
    }

    private function entitySearchResult(SalesChannelCollection $collection): EntitySearchResult&MockObject
    {
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn($collection);

        return $result;
    }

    private function templateChannel(bool $withAssociations, ?string $snippetSetId): SalesChannelEntity
    {
        $template = new SalesChannelEntity();
        $template->setId('t-id');
        $template->setUniqueIdentifier('t-id');
        $template->setLanguageId('l-id');
        $template->setCurrencyId('c-id');
        $template->setPaymentMethodId('pm-id');
        $template->setShippingMethodId('sm-id');
        $template->setCountryId('co-id');
        $template->setCustomerGroupId('cg-id');

        if ($withAssociations) {
            $language = new LanguageEntity();
            $language->setId('l-id');
            $currency = new CurrencyEntity();
            $currency->setId('c-id');
            $payment = new PaymentMethodEntity();
            $payment->setId('pm-id');
            $shipping = new ShippingMethodEntity();
            $shipping->setId('sm-id');
            $country = new CountryEntity();
            $country->setId('co-id');
            $template->setLanguages(new LanguageCollection([$language]));
            $template->setCurrencies(new CurrencyCollection([$currency]));
            $template->setPaymentMethods(new PaymentMethodCollection([$payment]));
            $template->setShippingMethods(new ShippingMethodCollection([$shipping]));
            $template->setCountries(new CountryCollection([$country]));
        }

        if ($snippetSetId !== null) {
            $domain = new SalesChannelDomainEntity();
            $domain->setId('domain-id');
            $domain->setUniqueIdentifier('domain-id');
            $domain->setUrl('https://template.example');
            $domain->setSnippetSetId($snippetSetId);
            $template->setDomains(new SalesChannelDomainCollection([$domain]));
        }

        return $template;
    }
}
