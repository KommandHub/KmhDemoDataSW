<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Service;

use Kommandhub\DemoData\Service\ChannelPricing;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\DeterministicValueGenerator;
use Kommandhub\DemoData\Service\ProductPayloadBuilder;
use Kommandhub\DemoData\Service\ProductReviewBuilder;
use Kommandhub\DemoData\Util\DemoDataConstants;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductPayloadBuilderTest extends TestCase
{
    private ProductPayloadBuilder $builder;

    protected function setUp(): void
    {
        $values = new DeterministicValueGenerator();

        $this->builder = new ProductPayloadBuilder(
            $values,
            new DemoIdGenerator(),
            new ProductReviewBuilder($values)
        );
    }

    public function testPayloadIsCompleteEnoughToBeSellable(): void
    {
        $payload = $this->build();

        $this->assertTrue(Uuid::isValid((string)$payload['id']));
        $this->assertSame('Halo ANC Over-Ear', $payload['name']);
        $this->assertTrue($payload['active']);
        $this->assertGreaterThan(0, $payload['stock']);
        $this->assertSame('manufacturer-id', $payload['manufacturerId']);
        $this->assertSame('tax-id', $payload['taxId']);
        $this->assertNotEmpty($payload['price']);
        $this->assertNotEmpty($payload['purchasePrices']);
        $this->assertNotEmpty($payload['categories']);
        $this->assertNotEmpty($payload['visibilities']);
    }

    public function testTheSameProductAlwaysProducesTheSamePayload(): void
    {
        $this->assertEquals($this->build(), $this->build());
    }

    public function testNetPriceIsDerivedFromTheTaxRate(): void
    {
        $payload = $this->build();
        /** @var array<string, mixed> $price */
        $price = $payload['price'][0];

        $this->assertSame(round(((float)$price['gross']) / 1.19, 2), $price['net']);
        $this->assertSame(0.99, round(((float)$price['gross']) - floor((float)$price['gross']), 2));
    }

    public function testProductIsVisibleOnlyInItsOwnSalesChannel(): void
    {
        $payload = $this->build();
        /** @var array<int, array<string, mixed>> $visibilities */
        $visibilities = $payload['visibilities'];

        $this->assertCount(1, $visibilities);
        $this->assertSame('sales-channel-id', $visibilities[0]['salesChannelId']);
        $this->assertSame(30, $visibilities[0]['visibility']);
    }

    public function testProductIsLinkedToEveryCategoryOnItsPath(): void
    {
        $payload = $this->build();

        $this->assertSame(
            [['id' => 'ancestor-id'], ['id' => 'leaf-id']],
            $payload['categories']
        );
    }

    public function testProductCarriesItsProvenanceInCustomFields(): void
    {
        $payload = $this->build();
        /** @var array<string, mixed> $customFields */
        $customFields = $payload['customFields'];

        $this->assertTrue($customFields[DemoDataConstants::FIELD_GENERATED]);
        $this->assertSame('flagship', $customFields[DemoDataConstants::FIELD_CHANNEL_KEY]);
        $this->assertStringContainsString('Halo ANC Over-Ear', (string)$customFields[DemoDataConstants::FIELD_SOURCE_KEY]);
    }

    public function testProductNumberIsNamespacedByChannelAndCategory(): void
    {
        $this->assertMatchesRegularExpression('/^KMH-FLA-HEAD[0-9A-F]{3}-001$/', (string)$this->build()['productNumber']);
    }

    /**
     * Two channels carrying a same-named category must not mint the same SKU.
     */
    public function testProductNumbersDifferBetweenChannels(): void
    {
        $flagship = $this->build('flagship');
        $trade = $this->build('trade');

        $this->assertNotSame($flagship['productNumber'], $trade['productNumber']);
        $this->assertNotSame($flagship['id'], $trade['id']);
    }

    public function testVariantChildrenCarryDistinctNumbersAndOptions(): void
    {
        $payload = $this->buildUntilVariants();

        /** @var array<int, array<string, mixed>> $children */
        $children = $payload['children'];
        $this->assertNotEmpty($children);

        $numbers = array_column($children, 'productNumber');
        $this->assertSame($numbers, array_unique($numbers));

        foreach ($children as $child) {
            $this->assertSame($payload['id'], $child['parentId']);
            $this->assertNotEmpty($child['options']);
            $this->assertNotEmpty($child['price']);
        }
    }

    public function testVariantOptionsAlsoAppearOnTheParentProperties(): void
    {
        $payload = $this->buildUntilVariants();

        /** @var array<int, array{optionId: string}> $settings */
        $settings = $payload['configuratorSettings'];
        /** @var array<int, array{id: string}> $properties */
        $properties = $payload['properties'];
        $propertyIds = array_column($properties, 'id');

        $this->assertNotEmpty($settings);

        foreach ($settings as $setting) {
            // Without this, Shopware renders a configurator that filters nothing.
            $this->assertContains($setting['optionId'], $propertyIds);
        }
    }

    /**
     * Reviews belong to the parent. Split across four colour variants they
     * average to nothing, and the storefront shows them on the parent anyway.
     */
    public function testVariantChildrenCarryNoReviewsOfTheirOwn(): void
    {
        $payload = $this->buildUntilVariants();

        /** @var array<int, array<string, mixed>> $children */
        $children = $payload['children'];

        foreach ($children as $child) {
            $this->assertArrayNotHasKey('productReviews', $child);
        }
    }

    public function testReviewsAreAttachedToTheProductAndItsSalesChannel(): void
    {
        for ($i = 1; $i <= 40; ++$i) {
            $payload = $this->builder->build(
                'flagship',
                'sales-channel-id',
                $this->leaf(),
                'Reviewed Product ' . $i,
                $i,
                'manufacturer-id',
                'Aurora Audio',
                'tax-id',
                19.0,
                $this->propertyGroups(),
                ['tag-a'],
                [],
                $this->pricing()
            );

            if (!isset($payload['productReviews'])) {
                continue;
            }

            /** @var array<int, array<string, mixed>> $reviews */
            $reviews = $payload['productReviews'];

            foreach ($reviews as $review) {
                $this->assertSame($payload['id'], $review['productId']);
                $this->assertSame('sales-channel-id', $review['salesChannelId']);
            }

            return;
        }

        self::fail('no product produced reviews in 40 attempts');
    }

    public function testProductWithoutMediaHasNoCover(): void
    {
        $payload = $this->build('flagship', []);

        $this->assertArrayNotHasKey('coverId', $payload);
        $this->assertArrayNotHasKey('media', $payload);
    }

    public function testAdvancedPricesCoverEveryQuantityTierWithoutOverlap(): void
    {
        $payload = $this->build();

        /** @var array<int, array<string, mixed>> $prices */
        $prices = $payload['prices'];
        $this->assertCount(3, $prices);

        $expectedStart = 1;

        foreach ($prices as $tier) {
            $this->assertSame('rule-id', $tier['ruleId']);
            $this->assertSame($payload['id'], $tier['productId']);
            $this->assertSame($expectedStart, $tier['quantityStart']);

            // A gap or an overlap between tiers is a pricing bug the storefront
            // renders as a missing or duplicated quantity break.
            $expectedStart = $tier['quantityEnd'] === null ? $expectedStart : $tier['quantityEnd'] + 1;
        }

        $this->assertNull($prices[2]['quantityEnd'], 'the top tier must be open-ended');
    }

    public function testBuyingMoreNeverCostsMorePerUnit(): void
    {
        /** @var array<int, array<string, mixed>> $prices */
        $prices = $this->build()['prices'];
        $previous = \PHP_FLOAT_MAX;

        foreach ($prices as $tier) {
            $gross = (float)$tier['price'][0]['gross'];

            $this->assertLessThanOrEqual($previous, $gross);
            $previous = $gross;
        }
    }

    public function testTheChannelPriceFactorIsApplied(): void
    {
        $full = $this->build();
        $discounted = $this->build('flagship', ['media-a'], ChannelPricing::of('rule-id', 0.5, [[1, null, 0.0]]));

        $this->assertSame(
            round(((float)$full['prices'][0]['price'][0]['gross']) * 0.5, 2),
            (float)$discounted['prices'][0]['price'][0]['gross']
        );
    }

    public function testVariantsCarryTheirOwnQuantityBreaks(): void
    {
        $payload = $this->buildUntilVariants();

        /** @var array<int, array<string, mixed>> $children */
        $children = $payload['children'];

        foreach ($children as $child) {
            $this->assertNotEmpty($child['prices']);

            foreach ($child['prices'] as $tier) {
                $this->assertSame($child['id'], $tier['productId'], 'a variant priced against its parent');
            }
        }
    }

    private function pricing(): ChannelPricing
    {
        return ChannelPricing::of('rule-id', 1.0, [[1, 9, 0.0], [10, 24, 0.1], [25, null, 0.2]]);
    }

    /**
     * @param array<int, string> $mediaIds
     *
     * @return array<string, mixed>
     */
    private function build(
        string $channelKey = 'flagship',
        array $mediaIds = ['media-a', 'media-b'],
        ?ChannelPricing $pricing = null
    ): array {
        $pricing ??= $this->pricing();

        return $this->builder->build(
            $channelKey,
            'sales-channel-id',
            $this->leaf(),
            'Halo ANC Over-Ear',
            1,
            'manufacturer-id',
            'Aurora Audio',
            'tax-id',
            19.0,
            $this->propertyGroups(),
            ['tag-a', 'tag-b', 'tag-c'],
            $mediaIds,
            $pricing
        );
    }

    /**
     * Only a share of products are variant parents, so walk product names until
     * one is — the alternative is asserting against whichever name happens to
     * hash into the variant bucket today.
     *
     * @return array<string, mixed>
     */
    private function buildUntilVariants(): array
    {
        for ($i = 1; $i <= 40; ++$i) {
            $payload = $this->builder->build(
                'flagship',
                'sales-channel-id',
                $this->leaf(),
                'Candidate Product ' . $i,
                $i,
                'manufacturer-id',
                'Aurora Audio',
                'tax-id',
                19.0,
                $this->propertyGroups(),
                ['tag-a'],
                [],
                $this->pricing()
            );

            if (isset($payload['children'])) {
                return $payload;
            }
        }

        self::fail('No variant parent produced in 40 attempts — the variant share is broken.');
    }

    /**
     * @return array{id: string, name: string, path: array<int, string>, ancestorIds: array<int, string>, spec: array<string, mixed>}
     */
    private function leaf(): array
    {
        return [
            'id' => 'leaf-id',
            'name' => 'Headphones & Earbuds',
            'path' => ['flagship', 'Electronics & Audio', 'Headphones & Earbuds'],
            'ancestorIds' => ['ancestor-id', 'leaf-id'],
            'spec' => [
                'name' => 'Headphones & Earbuds',
                'manufacturer' => 'aurora-audio',
                'price' => [49.0, 349.0],
                'properties' => ['colour', 'connectivity'],
                'variantAxis' => ['colour'],
                'features' => ['Hybrid active noise cancellation'],
                'products' => ['Halo ANC Over-Ear'],
            ],
        ];
    }

    /**
     * @return array<string, array{id: string, options: array<string, string>}>
     */
    private function propertyGroups(): array
    {
        return [
            'colour' => [
                'id' => 'group-colour',
                'options' => ['Black' => 'opt-black', 'White' => 'opt-white', 'Silver' => 'opt-silver'],
            ],
            'connectivity' => [
                'id' => 'group-connectivity',
                'options' => ['USB-C' => 'opt-usbc', 'Bluetooth 5.3' => 'opt-bt'],
            ],
        ];
    }
}
