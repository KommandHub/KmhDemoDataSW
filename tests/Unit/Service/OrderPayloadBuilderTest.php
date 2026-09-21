<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Service;

use Kommandhub\DemoData\Blueprint\PeopleBlueprint;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\DeterministicValueGenerator;
use Kommandhub\DemoData\Service\OrderPayloadBuilder;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;

/**
 * Shopware stores an order's totals rather than recomputing them, so an order
 * whose parts do not add up is a row that displays fine until somebody opens
 * the invoice. These are the sums.
 */
class OrderPayloadBuilderTest extends TestCase
{
    private const TAX_RATE = 19.0;

    private OrderPayloadBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new OrderPayloadBuilder(
            new DeterministicValueGenerator(),
            new DemoIdGenerator()
        );
    }

    public function testLineItemsAddUpToThePositionPrice(): void
    {
        foreach ($this->orders(30) as $order) {
            /** @var CartPrice $price */
            $price = $order['price'];
            /** @var array<int, array<string, mixed>> $lineItems */
            $lineItems = $order['lineItems'];

            $sum = 0.0;

            foreach ($lineItems as $line) {
                /** @var CalculatedPrice $linePrice */
                $linePrice = $line['price'];
                $sum += $linePrice->getTotalPrice();
            }

            $this->assertEqualsWithDelta($price->getPositionPrice(), round($sum, 2), 0.01);
        }
    }

    public function testTheTotalIsThePositionsPlusShipping(): void
    {
        foreach ($this->orders(30) as $order) {
            /** @var CartPrice $price */
            $price = $order['price'];
            /** @var CalculatedPrice $shipping */
            $shipping = $order['shippingCosts'];

            $this->assertEqualsWithDelta(
                $price->getPositionPrice() + $shipping->getTotalPrice(),
                $price->getTotalPrice(),
                0.01
            );
        }
    }

    public function testNetPlusTaxEqualsTheGrossTotal(): void
    {
        foreach ($this->orders(30) as $order) {
            /** @var CartPrice $price */
            $price = $order['price'];

            $this->assertEqualsWithDelta(
                $price->getTotalPrice(),
                $price->getNetPrice() + $price->getCalculatedTaxes()->getAmount(),
                0.02
            );
        }
    }

    public function testTheTransactionChargesTheOrderTotal(): void
    {
        foreach ($this->orders(20) as $order) {
            /** @var CartPrice $price */
            $price = $order['price'];
            /** @var array<int, array<string, mixed>> $transactions */
            $transactions = $order['transactions'];
            /** @var CalculatedPrice $amount */
            $amount = $transactions[0]['amount'];

            // A transaction for less than the order is how a demo shop ends up
            // showing a paid order with an outstanding balance.
            $this->assertEqualsWithDelta($price->getTotalPrice(), $amount->getTotalPrice(), 0.01);
        }
    }

    public function testShippingIsFreeAboveTheThreshold(): void
    {
        $shipping = PeopleBlueprint::shipping();
        $seenFree = 0;
        $seenCharged = 0;

        foreach ($this->orders(60) as $order) {
            /** @var CartPrice $price */
            $price = $order['price'];
            /** @var CalculatedPrice $costs */
            $costs = $order['shippingCosts'];

            if ($price->getPositionPrice() >= $shipping['freeFrom']) {
                $this->assertSame(0.0, $costs->getTotalPrice());
                ++$seenFree;
            } else {
                $this->assertSame($shipping['cost'], $costs->getTotalPrice());
                ++$seenCharged;
            }
        }

        $this->assertGreaterThan(0, $seenFree, 'no order ever qualified for free shipping');
        $this->assertGreaterThan(0, $seenCharged, 'no order was ever charged shipping');
    }

    public function testEveryOrderHasADeliveryATransactionAndBothAddresses(): void
    {
        foreach ($this->orders(20) as $order) {
            $this->assertCount(1, $order['deliveries']);
            $this->assertCount(1, $order['transactions']);
            $this->assertCount(1, $order['addresses']);
            $this->assertNotEmpty($order['billingAddressId']);
            $this->assertNotEmpty($order['deliveries'][0]['shippingOrderAddress']);
        }
    }

    public function testDeliveryPositionsCoverEveryLineItem(): void
    {
        foreach ($this->orders(20) as $order) {
            /** @var array<int, array<string, mixed>> $lineItems */
            $lineItems = $order['lineItems'];
            /** @var array<int, array<string, mixed>> $positions */
            $positions = $order['deliveries'][0]['positions'];

            $this->assertSame(
                array_column($lineItems, 'id'),
                array_column($positions, 'orderLineItemId')
            );
        }
    }

    public function testOrderNumbersAndIdsAreUnique(): void
    {
        $orders = $this->orders(60);
        $numbers = array_column($orders, 'orderNumber');
        $ids = array_column($orders, 'id');

        $this->assertSame($numbers, array_unique($numbers));
        $this->assertSame($ids, array_unique($ids));
    }

    public function testOrderNumbersDifferBetweenChannels(): void
    {
        $flagship = $this->build('flagship', 1);
        $trade = $this->build('trade', 1);

        $this->assertNotNull($flagship);
        $this->assertNotNull($trade);
        $this->assertNotSame($flagship['orderNumber'], $trade['orderNumber']);
        $this->assertNotSame($flagship['id'], $trade['id']);
    }

    public function testOrdersAreStableAcrossRuns(): void
    {
        $this->assertEquals($this->build('flagship', 7), $this->build('flagship', 7));
    }

    public function testOrderDatesFallInsideTheHistoryWindow(): void
    {
        $now = new \DateTimeImmutable();
        $oldest = $now->modify('-' . (PeopleBlueprint::ORDER_HISTORY_DAYS + 1) . ' days');

        foreach ($this->orders(40) as $order) {
            $placed = new \DateTimeImmutable((string)$order['orderDateTime']);

            $this->assertGreaterThan($oldest, $placed);
            $this->assertLessThan($now->modify('+1 day'), $placed);
        }
    }

    public function testAnEmptyCatalogueProducesNoOrder(): void
    {
        $this->assertNull($this->builder->build(
            'flagship',
            1,
            [],
            $this->channel(),
            $this->customer(),
            $this->stateIds(),
            self::TAX_RATE
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function orders(int $count): array
    {
        $orders = [];

        for ($i = 1; $i <= $count; ++$i) {
            $order = $this->build('flagship', $i);

            if ($order !== null) {
                $orders[] = $order;
            }
        }

        $this->assertNotEmpty($orders);

        return $orders;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function build(string $channelKey, int $index): ?array
    {
        return $this->builder->build(
            $channelKey,
            $index,
            $this->catalogue(),
            $this->channel(),
            $this->customer(),
            $this->stateIds(),
            self::TAX_RATE
        );
    }

    /**
     * Prices either side of the free-shipping threshold, so both branches occur.
     *
     * @return array<int, array{id: string, productNumber: string, name: string, gross: float}>
     */
    private function catalogue(): array
    {
        $catalogue = [];

        foreach ([9.99, 19.99, 34.99, 49.99, 89.99, 129.99, 249.99, 12.5] as $i => $gross) {
            $catalogue[] = [
                'id' => str_pad((string)($i + 1), 32, 'a', \STR_PAD_LEFT),
                'productNumber' => 'KMH-TEST-' . $i,
                'name' => 'Test Product ' . $i,
                'gross' => $gross,
            ];
        }

        return $catalogue;
    }

    /**
     * @return array<string, string>
     */
    private function channel(): array
    {
        return [
            'salesChannelId' => 'sales-channel-id',
            'languageId' => 'language-id',
            'currencyId' => 'currency-id',
            'countryId' => 'country-id',
            'paymentMethodId' => 'payment-method-id',
            'shippingMethodId' => 'shipping-method-id',
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function customer(): array
    {
        return [
            'id' => 'customer-id',
            'customerNumber' => 'KMH-FLA-00001',
            'firstName' => 'Amina',
            'lastName' => 'Okafor',
            'email' => 'amina.okafor@example.com',
            'company' => null,
            'street' => 'Hauptstraße 12',
            'zipcode' => '10115',
            'city' => 'Berlin',
            'salutationId' => 'salutation-id',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function stateIds(): array
    {
        return ['order' => 'order-state', 'transaction' => 'transaction-state', 'delivery' => 'delivery-state'];
    }
}
