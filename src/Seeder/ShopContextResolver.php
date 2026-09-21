<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Seeder;

use Kommandhub\DemoData\Exception\DemoDataException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;

/**
 * Reads the bits of an installation that customers and orders have to be
 * consistent with: which country the channel ships to, which payment and
 * shipping methods it offers, and the id behind a state machine state's name.
 *
 * None of it is created — all of it is configuration the merchant owns. An
 * order that names a payment method the channel does not offer is a row that
 * looks fine in the database and breaks the moment anyone opens it.
 *
 * Lookups are memoised: an order seeder asks for the same four state ids a few
 * hundred times per channel, and they cannot change mid-run.
 *
 * @phpstan-type ChannelContext array{salesChannelId: string, languageId: string, currencyId: string, countryId: string, paymentMethodId: string, shippingMethodId: string, customerGroupId: string}
 */
class ShopContextResolver
{
    /**
     * @var array<string, string>
     */
    private array $stateIds = [];

    private ?string $salutationId = null;

    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param EntityRepository<StateMachineStateCollection> $stateMachineStateRepository
     * @param EntityRepository<SalutationCollection> $salutationRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $stateMachineStateRepository,
        private readonly EntityRepository $salutationRepository
    ) {
    }

    /**
     * @return ChannelContext
     */
    public function channel(Context $context, string $salesChannelId): array
    {
        $criteria = new Criteria([$salesChannelId]);
        $criteria->addAssociation('countries');

        /** @var SalesChannelEntity|null $channel */
        $channel = $this->salesChannelRepository->search($criteria, $context)->getEntities()->first();

        if ($channel === null) {
            throw new DemoDataException(\sprintf('Sales channel %s disappeared mid-run.', $salesChannelId));
        }

        return [
            'salesChannelId' => $channel->getId(),
            'languageId' => $channel->getLanguageId(),
            'currencyId' => $channel->getCurrencyId(),
            'countryId' => $channel->getCountryId(),
            'paymentMethodId' => $channel->getPaymentMethodId(),
            'shippingMethodId' => $channel->getShippingMethodId(),
            'customerGroupId' => $channel->getCustomerGroupId(),
        ];
    }

    /**
     * @param string $machine e.g. OrderStates::STATE_MACHINE
     * @param string $technicalName e.g. 'completed'
     */
    public function stateId(Context $context, string $machine, string $technicalName): string
    {
        $cacheKey = $machine . '|' . $technicalName;

        if (isset($this->stateIds[$cacheKey])) {
            return $this->stateIds[$cacheKey];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('stateMachine.technicalName', $machine));
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));
        $criteria->setLimit(1);

        $id = $this->stateMachineStateRepository->searchIds($criteria, $context)->firstId();

        if ($id === null) {
            throw new DemoDataException(\sprintf('State "%s" of state machine "%s" does not exist.', $technicalName, $machine));
        }

        return $this->stateIds[$cacheKey] = $id;
    }

    /**
     * Salutations are installation data and every shop has at least
     * "not specified", so this reads rather than creates.
     */
    public function salutationId(Context $context): string
    {
        if ($this->salutationId !== null) {
            return $this->salutationId;
        }

        $preferred = new Criteria();
        $preferred->addFilter(new EqualsFilter('salutationKey', 'not_specified'));
        $preferred->setLimit(1);

        $id = $this->salutationRepository->searchIds($preferred, $context)->firstId()
            ?? $this->salutationRepository->searchIds((new Criteria())->setLimit(1), $context)->firstId();

        if ($id === null) {
            throw new DemoDataException('No salutation is configured, so customers cannot be created.');
        }

        return $this->salutationId = $id;
    }
}
