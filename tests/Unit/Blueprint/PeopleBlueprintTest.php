<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Blueprint;

use Kommandhub\DemoData\Blueprint\PeopleBlueprint;
use PHPUnit\Framework\TestCase;

class PeopleBlueprintTest extends TestCase
{
    public function testTheStateMixCoversEveryOrder(): void
    {
        $weights = array_column(PeopleBlueprint::orderStates(), 0);

        // Cumulative weights against a 1–100 roll: anything short of 100 leaves
        // orders falling through to the default with no state of their own.
        $this->assertSame(100, max($weights));
        $this->assertSame($weights, array_unique($weights));

        $previous = 0;

        foreach ($weights as $weight) {
            $this->assertGreaterThan($previous, $weight, 'cumulative weights must ascend');
            $previous = $weight;
        }
    }

    /**
     * A cancelled order that shipped and was paid is the kind of detail a sharp
     * prospect notices and a sales engineer cannot explain.
     */
    public function testCancelledOrdersAreNeitherPaidNorShipped(): void
    {
        foreach (PeopleBlueprint::orderStates() as [, $order, $transaction, $delivery]) {
            if ($order !== 'cancelled') {
                continue;
            }

            $this->assertNotSame('paid', $transaction);
            $this->assertNotSame('shipped', $delivery);
        }
    }

    public function testOpenOrdersHaveNotShipped(): void
    {
        foreach (PeopleBlueprint::orderStates() as [, $order, , $delivery]) {
            if ($order === 'open') {
                $this->assertSame('open', $delivery, 'an unprocessed order cannot have shipped');
            }
        }
    }

    public function testMostOrdersAreCompletedOrInProgress(): void
    {
        $healthy = 0;
        $previous = 0;

        foreach (PeopleBlueprint::orderStates() as [$weight, $order]) {
            $share = $weight - $previous;
            $previous = $weight;

            if (\in_array($order, ['completed', 'in_progress'], true)) {
                $healthy += $share;
            }
        }

        // A shop whose order list is mostly cancellations reads as a shop in
        // trouble, which is not the impression a demo should give.
        $this->assertGreaterThan(70, $healthy);
    }

    public function testNamePoolsAreLargeEnoughToAvoidObviousRepetition(): void
    {
        $combinations = \count(PeopleBlueprint::firstNames()) * \count(PeopleBlueprint::lastNames());

        $this->assertGreaterThan(PeopleBlueprint::CUSTOMERS_PER_CHANNEL * 5, $combinations);
    }

    public function testEveryPoolIsFreeOfDuplicates(): void
    {
        $this->assertSame(PeopleBlueprint::firstNames(), array_unique(PeopleBlueprint::firstNames()));
        $this->assertSame(PeopleBlueprint::lastNames(), array_unique(PeopleBlueprint::lastNames()));
        $this->assertSame(PeopleBlueprint::companies(), array_unique(PeopleBlueprint::companies()));
    }

    public function testEveryAddressIsComplete(): void
    {
        foreach (PeopleBlueprint::addresses() as $address) {
            $this->assertNotSame('', $address['street']);
            $this->assertNotSame('', $address['city']);
            $this->assertMatchesRegularExpression('/^\d{5}$/', $address['zip']);
        }
    }

    public function testFreeShippingThresholdIsAboveTheShippingCost(): void
    {
        $shipping = PeopleBlueprint::shipping();

        $this->assertGreaterThan($shipping['cost'], $shipping['freeFrom']);
    }

    public function testEnoughLoginAccountsToDemoButNotEnoughToBeSlow(): void
    {
        $this->assertGreaterThan(0, PeopleBlueprint::LOGIN_ACCOUNTS_PER_CHANNEL);
        $this->assertLessThan(PeopleBlueprint::CUSTOMERS_PER_CHANNEL, PeopleBlueprint::LOGIN_ACCOUNTS_PER_CHANNEL);
    }
}
