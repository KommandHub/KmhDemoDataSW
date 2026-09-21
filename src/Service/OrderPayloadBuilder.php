<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Service;

use Kommandhub\DemoData\Blueprint\PeopleBlueprint;
use Kommandhub\DemoData\Util\DemoDataConstants;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Builds one order, with its line items, delivery, transaction and addresses.
 *
 * The arithmetic is the part worth being careful about. Shopware stores an
 * order's totals rather than recomputing them, so a demo order whose line items
 * do not add up to its total is a row that displays happily until somebody
 * opens the invoice. Everything here is derived from the line items: the tax
 * comes off the gross unit prices, the position total is the sum of the lines,
 * and the order total is that plus shipping.
 *
 * Pure. The seeder supplies the products, the customer and the state ids.
 */
class OrderPayloadBuilder
{
    private const MIN_LINE_ITEMS = 1;
    private const MAX_LINE_ITEMS = 5;
    private const MAX_QUANTITY = 3;

    public function __construct(
        private readonly DeterministicValueGenerator $values,
        private readonly DemoIdGenerator $ids
    ) {
    }

    /**
     * @param array<int, array{id: string, productNumber: string, name: string, gross: float}> $catalogue products the channel actually sells
     * @param array{salesChannelId: string, languageId: string, currencyId: string, countryId: string, paymentMethodId: string, shippingMethodId: string} $channel
     * @param array{id: string, customerNumber: string, firstName: string, lastName: string, email: string, company: string|null, street: string, zipcode: string, city: string, salutationId: string} $customer
     * @param array{order: string, transaction: string, delivery: string} $stateIds
     *
     * @return array<string, mixed>|null null when the channel has nothing to sell
     */
    public function build(
        string $channelKey,
        int $index,
        array $catalogue,
        array $channel,
        array $customer,
        array $stateIds,
        float $taxRate
    ): ?array {
        if ($catalogue === []) {
            return null;
        }

        $key = $this->ids->key('order', $channelKey, (string)$index);
        $orderId = $this->ids->id('order', $channelKey, (string)$index);

        $lineItems = $this->lineItems($key, $orderId, $catalogue, $taxRate);

        if ($lineItems === []) {
            return null; // @codeCoverageIgnore
        }

        $positionPrice = round(array_sum(array_column($lineItems, 'grossTotal')), 2);
        $shipping = $this->shippingCosts($positionPrice, $taxRate);
        $totalPrice = round($positionPrice + $shipping->getTotalPrice(), 2);
        $totalTax = round(array_sum(array_column($lineItems, 'taxTotal')) + $this->taxOf($shipping), 2);

        $billingAddressId = $this->ids->id('order-address', $channelKey, (string)$index, 'billing');
        $orderedAt = $this->orderedAt($key);

        return [
            'id' => $orderId,
            'orderNumber' => $this->orderNumber($channelKey, $index),
            'salesChannelId' => $channel['salesChannelId'],
            'languageId' => $channel['languageId'],
            'currencyId' => $channel['currencyId'],
            'currencyFactor' => 1.0,
            'orderDateTime' => $orderedAt,
            'stateId' => $stateIds['order'],
            'price' => new CartPrice(
                round($totalPrice - $totalTax, 2),
                $totalPrice,
                $positionPrice,
                $this->taxCollection($totalTax, $taxRate, $totalPrice),
                $this->taxRules($taxRate),
                CartPrice::TAX_STATE_GROSS
            ),
            'shippingCosts' => $shipping,
            'itemRounding' => $this->rounding(),
            'totalRounding' => $this->rounding(),
            'billingAddressId' => $billingAddressId,
            'addresses' => [
                $this->orderAddress($billingAddressId, $orderId, $customer, $channel),
            ],
            'orderCustomer' => [
                'id' => $this->ids->id('order-customer', $channelKey, (string)$index),
                'customerId' => $customer['id'],
                'email' => $customer['email'],
                'firstName' => $customer['firstName'],
                'lastName' => $customer['lastName'],
                'salutationId' => $customer['salutationId'],
                'customerNumber' => $customer['customerNumber'],
                'company' => $customer['company'],
            ],
            'lineItems' => array_map(
                static fn (array $line): array => $line['payload'],
                $lineItems
            ),
            'deliveries' => [$this->delivery($channelKey, $index, $orderId, $customer, $channel, $stateIds, $shipping, $lineItems, $orderedAt)],
            'transactions' => [[
                'id' => $this->ids->id('order-transaction', $channelKey, (string)$index),
                'paymentMethodId' => $channel['paymentMethodId'],
                'stateId' => $stateIds['transaction'],
                'amount' => new CalculatedPrice(
                    $totalPrice,
                    $totalPrice,
                    $this->taxCollection($totalTax, $taxRate, $totalPrice),
                    $this->taxRules($taxRate)
                ),
            ]],
            'customFields' => [
                DemoDataConstants::FIELD_SOURCE_KEY => $key,
                DemoDataConstants::FIELD_GENERATED => true,
                DemoDataConstants::FIELD_CHANNEL_KEY => $channelKey,
            ],
        ];
    }

    /**
     * @param array<int, array{id: string, productNumber: string, name: string, gross: float}> $catalogue
     *
     * @return array<int, array{payload: array<string, mixed>, grossTotal: float, taxTotal: float, id: string}>
     */
    private function lineItems(string $key, string $orderId, array $catalogue, float $taxRate): array
    {
        $picked = $this->values->pickMany(
            $key . '|products',
            $catalogue,
            $this->values->int($key . '|line-count', self::MIN_LINE_ITEMS, self::MAX_LINE_ITEMS)
        );

        $lines = [];
        $position = 0;

        foreach ($picked as $product) {
            ++$position;
            $lineKey = $key . '|line|' . $product['id'];
            $quantity = $this->values->int($lineKey . '|qty', 1, self::MAX_QUANTITY);

            $unitGross = $product['gross'];
            $grossTotal = round($unitGross * $quantity, 2);
            $taxTotal = round($grossTotal - ($grossTotal / (1 + ($taxRate / 100))), 2);

            $price = new CalculatedPrice(
                $unitGross,
                $grossTotal,
                $this->taxCollection($taxTotal, $taxRate, $grossTotal),
                $this->taxRules($taxRate),
                $quantity
            );

            $lineId = Uuid::fromStringToHex($lineKey);

            $lines[] = [
                'id' => $lineId,
                'grossTotal' => $grossTotal,
                'taxTotal' => $taxTotal,
                'payload' => [
                    'id' => $lineId,
                    'orderId' => $orderId,
                    'identifier' => $product['id'],
                    'referencedId' => $product['id'],
                    'productId' => $product['id'],
                    'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                    'label' => $product['name'],
                    'quantity' => $quantity,
                    'position' => $position,
                    'good' => true,
                    'removable' => false,
                    'stackable' => true,
                    'price' => $price,
                    'priceDefinition' => new QuantityPriceDefinition($unitGross, $this->taxRules($taxRate), $quantity),
                    // What the storefront shows on the order detail page.
                    'payload' => ['productNumber' => $product['productNumber']],
                ],
            ];
        }

        return $lines;
    }

    /**
     * @param array{id: string, firstName: string, lastName: string, company: string|null, street: string, zipcode: string, city: string, salutationId: string} $customer
     * @param array{countryId: string, shippingMethodId: string} $channel
     * @param array{order: string, transaction: string, delivery: string} $stateIds
     * @param array<int, array{id: string, grossTotal: float, taxTotal: float, payload: array<string, mixed>}> $lineItems
     *
     * @return array<string, mixed>
     */
    private function delivery(
        string $channelKey,
        int $index,
        string $orderId,
        array $customer,
        array $channel,
        array $stateIds,
        CalculatedPrice $shipping,
        array $lineItems,
        string $orderedAt
    ): array {
        $shippingAddressId = $this->ids->id('order-address', $channelKey, (string)$index, 'shipping');
        $shipped = (new \DateTimeImmutable($orderedAt))->modify('+2 days');

        return [
            'id' => $this->ids->id('order-delivery', $channelKey, (string)$index),
            'stateId' => $stateIds['delivery'],
            'shippingMethodId' => $channel['shippingMethodId'],
            'shippingOrderAddressId' => $shippingAddressId,
            'shippingOrderAddress' => $this->orderAddress($shippingAddressId, $orderId, $customer, $channel),
            'shippingCosts' => $shipping,
            'shippingDateEarliest' => $shipped->format(Defaults::STORAGE_DATE_FORMAT),
            'shippingDateLatest' => $shipped->modify('+3 days')->format(Defaults::STORAGE_DATE_FORMAT),
            'positions' => array_map(
                static fn (array $line): array => [
                    'orderLineItemId' => $line['id'],
                    'price' => $line['payload']['price'],
                ],
                $lineItems
            ),
        ];
    }

    /**
     * @param array{firstName: string, lastName: string, company: string|null, street: string, zipcode: string, city: string, salutationId: string} $customer
     * @param array{countryId: string} $channel
     *
     * @return array<string, mixed>
     */
    private function orderAddress(string $addressId, string $orderId, array $customer, array $channel): array
    {
        return [
            'id' => $addressId,
            'orderId' => $orderId,
            'salutationId' => $customer['salutationId'],
            'firstName' => $customer['firstName'],
            'lastName' => $customer['lastName'],
            'company' => $customer['company'],
            'street' => $customer['street'],
            'zipcode' => $customer['zipcode'],
            'city' => $customer['city'],
            'countryId' => $channel['countryId'],
        ];
    }

    private function shippingCosts(float $positionPrice, float $taxRate): CalculatedPrice
    {
        $shipping = PeopleBlueprint::shipping();
        $gross = $positionPrice >= $shipping['freeFrom'] ? 0.0 : $shipping['cost'];
        $tax = round($gross - ($gross / (1 + ($taxRate / 100))), 2);

        return new CalculatedPrice(
            $gross,
            $gross,
            $this->taxCollection($tax, $taxRate, $gross),
            $this->taxRules($taxRate)
        );
    }

    private function taxOf(CalculatedPrice $price): float
    {
        return $price->getCalculatedTaxes()->getAmount();
    }

    private function taxCollection(float $tax, float $taxRate, float $price): CalculatedTaxCollection
    {
        return new CalculatedTaxCollection([new CalculatedTax($tax, $taxRate, $price)]);
    }

    private function taxRules(float $taxRate): TaxRuleCollection
    {
        return new TaxRuleCollection([new TaxRule($taxRate, 100.0)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rounding(): array
    {
        /** @var array<string, mixed> $rounding */
        $rounding = json_decode(
            json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        return $rounding;
    }

    /**
     * Namespaced per channel so two channels cannot mint the same number, which
     * Shopware requires to be unique across the shop.
     */
    private function orderNumber(string $channelKey, int $index): string
    {
        return \sprintf('KMH%s%05d', strtoupper(substr($channelKey, 0, 2)), $index);
    }

    private function orderedAt(string $key): string
    {
        return (new \DateTimeImmutable('today'))
            ->modify(\sprintf('-%d days', $this->values->int($key . '|placed', 1, PeopleBlueprint::ORDER_HISTORY_DAYS)))
            ->modify(\sprintf('+%d hours', $this->values->int($key . '|hour', 7, 21)))
            ->modify(\sprintf('+%d minutes', $this->values->int($key . '|minute', 0, 59)))
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
