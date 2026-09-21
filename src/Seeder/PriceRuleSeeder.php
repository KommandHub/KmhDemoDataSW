<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Seeder;

use Kommandhub\DemoData\Service\EntityResolver;
use Kommandhub\DemoData\Service\SeedReport;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\SalesChannelRule;

/**
 * One pricing rule per sales channel.
 *
 * Shopware's advanced prices are scoped by *rule*, never directly by sales
 * channel — the product price table has a `rule_id` and no `sales_channel_id`.
 * Per-channel pricing is therefore a rule whose only condition is "the customer
 * is shopping in this sales channel", with the channel's price list hung off it.
 *
 * The rules this creates are ordinary rules: they show up under Settings >
 * Rule builder and can be edited or reused for promotions and shipping costs
 * like any other.
 */
class PriceRuleSeeder
{
    /**
     * @param EntityRepository<RuleCollection> $ruleRepository
     */
    public function __construct(
        private readonly EntityRepository $ruleRepository,
        private readonly EntityResolver $resolver
    ) {
    }

    /**
     * @return string the rule id products in this channel price against
     */
    public function resolve(
        Context $context,
        SeedReport $report,
        string $channelKey,
        string $channelName,
        string $salesChannelId
    ): string {
        $ruleId = $this->resolver->ids()->id('price-rule', $channelKey);

        $resolved = $this->resolver->resolveByField(
            $this->ruleRepository,
            $context,
            $ruleId,
            'name',
            $this->ruleName($channelName)
        );

        if ($resolved->exists) {
            $resolved->adopted ? $report->adopted('price_rule') : $report->reused('price_rule');

            return $resolved->id;
        }

        $this->ruleRepository->create([[
            'id' => $ruleId,
            'name' => $this->ruleName($channelName),
            'description' => \sprintf(
                'Matches customers shopping in "%s". Used by the generated advanced price lists.',
                $channelName
            ),
            // Low priority: a demo price list must lose to whatever pricing
            // rules the merchant has already set up, not override them.
            'priority' => 1,
            'conditions' => [[
                'id' => $this->resolver->ids()->id('price-rule-condition', $channelKey),
                'type' => (new AndRule())->getName(),
                'position' => 0,
                'children' => [[
                    'id' => $this->resolver->ids()->id('price-rule-condition', $channelKey, 'sales-channel'),
                    'type' => SalesChannelRule::RULE_NAME,
                    'position' => 0,
                    'value' => [
                        'operator' => Rule::OPERATOR_EQ,
                        'salesChannelIds' => [$salesChannelId],
                    ],
                ]],
            ]],
        ]], $context);

        $report->created('price_rule');

        return $ruleId;
    }

    private function ruleName(string $channelName): string
    {
        return 'Demo pricing — ' . $channelName;
    }
}
