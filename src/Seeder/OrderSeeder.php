<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Seeder;

use Kommandhub\DemoData\Blueprint\PeopleBlueprint;
use Kommandhub\DemoData\Service\BatchWriter;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\DeterministicValueGenerator;
use Kommandhub\DemoData\Service\OrderPayloadBuilder;
use Kommandhub\DemoData\Service\SeedReport;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * Order history for a sales channel.
 *
 * An admin dashboard reading zero turnover is the first thing anyone sees when
 * a demo shop is opened, so this exists mostly to make that screen honest: a
 * year of orders, spread across dates and states, belonging to real customers
 * and referencing products the channel actually sells.
 *
 * Orders are written whole — line items, delivery, transaction and both
 * addresses in a single payload. Shopware stores an order's totals rather than
 * recomputing them, so a half-written order is worse than none at all.
 *
 * Nothing here transitions a state machine: the states are set directly, which
 * is what the merchant sees and avoids firing a year's worth of order-placed
 * emails and flow-builder actions on a seed run.
 */
class OrderSeeder
{
    private const BATCH_SIZE = 25;

    /** Products sampled per channel to draw line items from. */
    private const CATALOGUE_SAMPLE = 200;

    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     * @param EntityRepository<ProductCollection> $productRepository
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $customerRepository,
        private readonly OrderPayloadBuilder $payloadBuilder,
        private readonly BatchWriter $batch,
        private readonly ShopContextResolver $shop,
        private readonly DeterministicValueGenerator $values,
        private readonly DemoIdGenerator $ids
    ) {
    }

    /**
     * @param array{salesChannelId: string, languageId: string, currencyId: string, countryId: string, paymentMethodId: string, shippingMethodId: string} $channel
     * @param array<int, string> $productIds products this channel sells
     * @param array<int, string> $customerIds customers of this channel
     */
    public function seed(
        Context $context,
        SeedReport $report,
        string $channelKey,
        array $channel,
        array $productIds,
        array $customerIds,
        float $taxRate,
        int $count
    ): void {
        if ($productIds === [] || $customerIds === []) {
            $report->skipped('order', $count);

            return;
        }

        $catalogue = $this->catalogue($context, $channelKey, $productIds);
        $customers = $this->customers($context, $customerIds);

        if ($catalogue === [] || $customers === []) {
            $report->skipped('order', $count);

            return;
        }

        $payloads = [];

        for ($index = 1; $index <= $count; ++$index) {
            $key = $this->ids->key('order', $channelKey, (string)$index);

            $payload = $this->payloadBuilder->build(
                $channelKey,
                $index,
                $catalogue,
                $channel,
                $this->values->pick($key . '|customer', $customers),
                $this->stateIds($context, $key),
                $taxRate
            );

            if ($payload !== null) {
                /** @var string $id */
                $id = $payload['id'];
                $payloads[$id] = $payload;
            }
        }

        $this->write($context, $report, $payloads);
    }

    /**
     * @param array<string, array<string, mixed>> $payloads
     */
    private function write(Context $context, SeedReport $report, array $payloads): void
    {
        if ($payloads === []) {
            return;
        }

        $known = $this->orderRepository->searchIds(new Criteria(array_keys($payloads)), $context)->getIds();
        $creates = [];

        foreach ($payloads as $id => $payload) {
            if (\in_array($id, $known, true)) {
                $report->reused('order');

                continue;
            }

            $creates[] = $payload;
            $report->created('order');
            $report->created(
                'order_line_item',
                \is_array($payload['lineItems'] ?? null) ? \count($payload['lineItems']) : 0
            );
        }

        // Small batches: an order payload carries its line items, delivery,
        // transaction and two addresses, so twenty-five of them is already a
        // large write.
        $this->batch->create($this->orderRepository, $creates, $context, self::BATCH_SIZE);
    }

    /**
     * @return array{order: string, transaction: string, delivery: string}
     */
    private function stateIds(Context $context, string $key): array
    {
        $roll = $this->values->int($key . '|state', 1, 100);
        [, $order, $transaction, $delivery] = [0, 'completed', 'paid', 'shipped'];

        foreach (PeopleBlueprint::orderStates() as [$threshold, $orderState, $transactionState, $deliveryState]) {
            if ($roll <= $threshold) {
                [$order, $transaction, $delivery] = [$orderState, $transactionState, $deliveryState];

                break;
            }
        }

        return [
            'order' => $this->shop->stateId($context, OrderStates::STATE_MACHINE, $order),
            'transaction' => $this->shop->stateId($context, OrderTransactionStates::STATE_MACHINE, $transaction),
            'delivery' => $this->shop->stateId($context, OrderDeliveryStates::STATE_MACHINE, $delivery),
        ];
    }

    /**
     * A sample of the channel's products with the three things a line item
     * needs. Sampling rather than loading all of them: two thousand products
     * per channel is a lot of rows to hold just to pick five per order.
     *
     * @param array<int, string> $productIds
     *
     * @return array<int, array{id: string, productNumber: string, name: string, gross: float}>
     */
    private function catalogue(Context $context, string $channelKey, array $productIds): array
    {
        $sample = $this->values->pickMany($channelKey . '|order-catalogue', $productIds, self::CATALOGUE_SAMPLE);
        $catalogue = [];

        // One query: the sample is small enough that chunking it would only
        // trade a single round trip for several.
        $found = $this->productRepository->search(new Criteria($sample), $context)->getEntities();

        foreach ($found as $product) {
            /** @var ProductEntity $product */
            $gross = $product->getCurrencyPrice($context->getCurrencyId())?->getGross();
            $name = $product->getTranslation('name');

            if ($gross === null || !\is_string($name)) {
                continue;
            }

            $catalogue[] = [
                'id' => $product->getId(),
                'productNumber' => $product->getProductNumber(),
                'name' => $name,
                'gross' => $gross,
            ];
        }

        return $catalogue;
    }

    /**
     * @param array<int, string> $customerIds
     *
     * @return array<int, array{id: string, customerNumber: string, firstName: string, lastName: string, email: string, company: string|null, street: string, zipcode: string, city: string, salutationId: string}>
     */
    private function customers(Context $context, array $customerIds): array
    {
        $criteria = new Criteria($customerIds);
        $criteria->addAssociation('defaultBillingAddress');

        $customers = [];
        $found = $this->customerRepository->search($criteria, $context)->getEntities();

        foreach ($found as $customer) {
            /** @var CustomerEntity $customer */
            $address = $customer->getDefaultBillingAddress();
            $salutationId = $customer->getSalutationId();

            // An order has to carry a full address; a customer without one
            // cannot be ordered for, and inventing one here would put a second
            // address on the order than the one on the account.
            if ($address === null || $salutationId === null) {
                continue;
            }

            $customers[] = [
                'id' => $customer->getId(),
                'customerNumber' => $customer->getCustomerNumber(),
                'firstName' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'email' => $customer->getEmail(),
                'company' => $customer->getCompany(),
                'street' => $address->getStreet(),
                'zipcode' => $address->getZipcode() ?? '',
                'city' => $address->getCity(),
                'salutationId' => $salutationId,
            ];
        }

        return $customers;
    }
}
