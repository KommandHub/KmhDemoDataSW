<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Seeder;

use Kommandhub\DemoData\Exception\DemoDataException;
use Kommandhub\DemoData\Seeder\ShopContextResolver;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class ShopContextResolverTest extends TestCase
{
    private EntityRepository $salesChannelRepository;
    private EntityRepository $stateMachineStateRepository;
    private EntityRepository $salutationRepository;
    private ShopContextResolver $resolver;
    private Context $context;

    protected function setUp(): void
    {
        $this->salesChannelRepository = $this->createMock(EntityRepository::class);
        $this->stateMachineStateRepository = $this->createMock(EntityRepository::class);
        $this->salutationRepository = $this->createMock(EntityRepository::class);
        $this->resolver = new ShopContextResolver(
            $this->salesChannelRepository,
            $this->stateMachineStateRepository,
            $this->salutationRepository
        );
        $this->context = Context::createDefaultContext();
    }

    public function testChannelSuccessful(): void
    {
        $channel = new SalesChannelEntity();
        $channel->setId('sc-id');
        $channel->setLanguageId('l-id');
        $channel->setCurrencyId('curr-id');
        $channel->setCountryId('cnt-id');
        $channel->setPaymentMethodId('p-id');
        $channel->setShippingMethodId('s-id');
        $channel->setCustomerGroupId('cg-id');

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new SalesChannelCollection([$channel]));
        $this->salesChannelRepository->method('search')->willReturn($searchResult);

        $result = $this->resolver->channel($this->context, 'sc-id');

        $this->assertSame('sc-id', $result['salesChannelId']);
        $this->assertSame('l-id', $result['languageId']);
    }

    public function testChannelThrowsWhenNotFound(): void
    {
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new SalesChannelCollection([]));
        $this->salesChannelRepository->method('search')->willReturn($searchResult);

        $this->expectException(DemoDataException::class);
        $this->resolver->channel($this->context, 'missing');
    }

    public function testStateIdSuccessfulAndMemoized(): void
    {
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn('state-id');
        $this->stateMachineStateRepository->method('searchIds')->willReturn($idSearchResult);

        $this->assertSame('state-id', $this->resolver->stateId($this->context, 'order', 'open'));
        $this->assertSame('state-id', $this->resolver->stateId($this->context, 'order', 'open'));
    }

    public function testStateIdThrowsWhenTheStateDoesNotExist(): void
    {
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn(null);
        $this->stateMachineStateRepository->method('searchIds')->willReturn($idSearchResult);

        $this->expectException(DemoDataException::class);
        $this->resolver->stateId($this->context, 'order', 'missing');
    }

    public function testSalutationIdPrefersNotSpecifiedAndFallsBackToAnySalutation(): void
    {
        $preferred = $this->createMock(IdSearchResult::class);
        $preferred->method('firstId')->willReturn('preferred-id');
        $this->salutationRepository->method('searchIds')->willReturn($preferred);
        $this->assertSame('preferred-id', $this->resolver->salutationId($this->context));
        $this->assertSame('preferred-id', $this->resolver->salutationId($this->context));

        $fallbackRepository = $this->createMock(EntityRepository::class);
        $fallbackRepository->method('searchIds')->willReturnOnConsecutiveCalls(
            $this->idResult(null),
            $this->idResult('fallback-id')
        );
        $resolver = new ShopContextResolver($this->salesChannelRepository, $this->stateMachineStateRepository, $fallbackRepository);
        $this->assertSame('fallback-id', $resolver->salutationId($this->context));
    }

    public function testSalutationIdThrowsWhenNoSalutationExists(): void
    {
        $resolver = new ShopContextResolver($this->salesChannelRepository, $this->stateMachineStateRepository, $this->salutationRepository);
        $this->salutationRepository->method('searchIds')->willReturn($this->idResult(null));

        $this->expectException(DemoDataException::class);
        $resolver->salutationId($this->context);
    }

    private function idResult(?string $firstId): IdSearchResult
    {
        $result = $this->createMock(IdSearchResult::class);
        $result->method('firstId')->willReturn($firstId);

        return $result;
    }
}
