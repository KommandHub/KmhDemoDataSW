<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Seeder;

use Kommandhub\DemoData\Blueprint\PeopleBlueprint;
use Kommandhub\DemoData\Service\BatchWriter;
use Kommandhub\DemoData\Service\CustomerPayloadBuilder;
use Kommandhub\DemoData\Service\EntityResolver;
use Kommandhub\DemoData\Service\SeedReport;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * The customers a sales channel has.
 *
 * Existence is decided per customer rather than per batch, because a customer
 * number and an email address are both unique in Shopware: creating one that is
 * already there fails the whole write, not just that row.
 *
 * Returns the ids it resolved, since orders have to belong to somebody.
 */
class CustomerSeeder
{
    private const BATCH_SIZE = 50;

    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly CustomerPayloadBuilder $payloadBuilder,
        private readonly BatchWriter $batch,
        private readonly EntityResolver $resolver
    ) {
    }

    /**
     * @param array{salesChannelId: string, languageId: string, countryId: string, paymentMethodId: string} $channel
     *
     * @return array<int, string> customer ids, in a stable order
     */
    public function seed(
        Context $context,
        SeedReport $report,
        string $channelKey,
        array $channel,
        string $customerGroupId,
        string $salutationId,
        int $count
    ): array {
        $payloads = [];
        $ids = [];

        for ($index = 1; $index <= $count; ++$index) {
            $payload = $this->payloadBuilder->build(
                $channelKey,
                $index,
                $channel,
                $customerGroupId,
                $salutationId,
                // Only the trade channel keeps business accounts — a wholesale
                // customer list of private individuals reads wrong.
                $channelKey === 'trade',
                $index <= PeopleBlueprint::LOGIN_ACCOUNTS_PER_CHANNEL
            );

            /** @var string $id */
            $id = $payload['id'];
            $ids[] = $id;
            $payloads[$id] = $payload;
        }

        $known = $this->customerRepository->searchIds(new Criteria(array_keys($payloads)), $context)->getIds();
        $creates = [];

        foreach ($payloads as $id => $payload) {
            if (\in_array($id, $known, true)) {
                $report->reused('customer');

                continue;
            }

            $creates[] = $payload;
            $report->created('customer');
            $report->created(
                'customer_address',
                \is_array($payload['addresses'] ?? null) ? \count($payload['addresses']) : 0
            );
        }

        $this->batch->create($this->customerRepository, $creates, $context, self::BATCH_SIZE);

        return $ids;
    }

    /**
     * The accounts that can actually be logged into, for the command to print.
     *
     * @return array<int, string>
     */
    public function loginEmails(string $channelKey): array
    {
        $emails = [];

        for ($index = 1; $index <= PeopleBlueprint::LOGIN_ACCOUNTS_PER_CHANNEL; ++$index) {
            $emails[] = $this->payloadBuilder->loginEmail($channelKey, $index);
        }

        return $emails;
    }

    public function resolver(): EntityResolver
    {
        return $this->resolver;
    }
}
