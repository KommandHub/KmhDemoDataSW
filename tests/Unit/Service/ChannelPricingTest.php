<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Service;

use Kommandhub\DemoData\Blueprint\DemoBlueprint;
use Kommandhub\DemoData\Service\ChannelPricing;
use PHPUnit\Framework\TestCase;

class ChannelPricingTest extends TestCase
{
    public function testFromBlueprintUsesExplicitValues(): void
    {
        $pricing = ChannelPricing::fromBlueprint('rule-id', [
            'priceFactor' => '1.25',
            'bulkTiers' => [[1, 4, 0.0], [5, null, 0.15]],
        ]);

        $this->assertSame('rule-id', $pricing->ruleId);
        $this->assertSame(1.25, $pricing->factor);
        $this->assertSame([[1, 4, 0.0], [5, null, 0.15]], $pricing->tiers);
    }

    public function testFromBlueprintFallsBackToDefaults(): void
    {
        $pricing = ChannelPricing::fromBlueprint('rule-id', ['priceFactor' => 'not-numeric']);

        $this->assertSame(1.0, $pricing->factor);
        $this->assertSame(DemoBlueprint::bulkTiers(), $pricing->tiers);
    }

    public function testOfReturnsExplicitValueObject(): void
    {
        $pricing = ChannelPricing::of('other-rule', 0.9, [[1, null, 0.1]]);

        $this->assertSame('other-rule', $pricing->ruleId);
        $this->assertSame(0.9, $pricing->factor);
        $this->assertSame([[1, null, 0.1]], $pricing->tiers);
    }
}
