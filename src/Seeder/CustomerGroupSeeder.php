<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Seeder;

use Kommandhub\DemoData\Service\EntityResolver;
use Kommandhub\DemoData\Service\SeedReport;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * The trade channel's B2B customer group.
 *
 * A wholesale channel whose customers all sit in the default retail group shows
 * gross prices to trade buyers, which contradicts the whole channel. This adds
 * one net-price group; every other channel keeps the installation's own default
 * group, because a shop only needs as many customer groups as it has ways of
 * pricing.
 */
class CustomerGroupSeeder
{
    private const B2B_KEY = 'trade';
    private const B2B_NAME = 'Trade (net prices)';

    /**
     * @param EntityRepository<CustomerGroupCollection> $customerGroupRepository
     */
    public function __construct(
        private readonly EntityRepository $customerGroupRepository,
        private readonly EntityResolver $resolver
    ) {
    }

    /**
     * @param string $fallbackGroupId the channel's configured group, used by every non-trade channel
     */
    public function resolve(Context $context, SeedReport $report, string $channelKey, string $fallbackGroupId): string
    {
        if ($channelKey !== self::B2B_KEY) {
            $report->reused('customer_group');

            return $fallbackGroupId;
        }

        $groupId = $this->resolver->ids()->id('customer-group', self::B2B_KEY);

        $resolved = $this->resolver->resolveByField(
            $this->customerGroupRepository,
            $context,
            $groupId,
            'name',
            self::B2B_NAME
        );

        if ($resolved->exists) {
            $resolved->adopted ? $report->adopted('customer_group') : $report->reused('customer_group');

            return $resolved->id;
        }

        $this->customerGroupRepository->create([[
            'id' => $groupId,
            'name' => self::B2B_NAME,
            // The only setting that matters here: trade buyers see net.
            'displayGross' => false,
            'registrationActive' => false,
        ]], $context);

        $report->created('customer_group');

        return $groupId;
    }
}
