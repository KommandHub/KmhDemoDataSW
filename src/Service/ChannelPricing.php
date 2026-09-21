<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Service;

use Kommandhub\DemoData\Blueprint\DemoBlueprint;

/**
 * What one sales channel charges: the rule its advanced prices hang off, a
 * factor applied to every list price, and its quantity breaks.
 *
 * Passed around as one value rather than three loose arguments because they are
 * only ever meaningful together — a rule id without its ladder prices nothing,
 * and a ladder without its rule applies to everyone.
 */
final class ChannelPricing
{
    /**
     * @param array<int, array{0: int, 1: int|null, 2: float}> $tiers [from, to, discount]
     */
    private function __construct(
        public readonly string $ruleId,
        public readonly float $factor,
        public readonly array $tiers
    ) {
    }

    /**
     * @param array<string, mixed> $channel blueprint sales-channel definition
     */
    public static function fromBlueprint(string $ruleId, array $channel): self
    {
        $factor = $channel['priceFactor'] ?? 1.0;
        /** @var array<int, array{0: int, 1: int|null, 2: float}> $tiers */
        $tiers = \is_array($channel['bulkTiers'] ?? null) && $channel['bulkTiers'] !== []
            ? array_values($channel['bulkTiers'])
            : DemoBlueprint::bulkTiers();

        return new self($ruleId, \is_numeric($factor) ? (float)$factor : 1.0, $tiers);
    }

    /**
     * @param array<int, array{0: int, 1: int|null, 2: float}> $tiers
     */
    public static function of(string $ruleId, float $factor, array $tiers): self
    {
        return new self($ruleId, $factor, $tiers);
    }
}
