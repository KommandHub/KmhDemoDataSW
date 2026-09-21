<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Service;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Kommandhub\DemoData\Util\DemoDataConstants;

/**
 * Turns one blueprint product into a complete Shopware product payload.
 *
 * Everything that varies — price, stock, which properties apply, whether the
 * product has variants — is derived from the product's own key, so the payload
 * for a given product is byte-identical on every run and on every machine. That
 * is what lets the seeder compare a second run against the first and honestly
 * claim nothing changed.
 *
 * No DAL access here on purpose: a pure builder is the part of this plugin that
 * can be unit tested without booting a kernel.
 */
class ProductPayloadBuilder
{
    private const VARIANT_SHARE_PERCENT = 40;
    private const MAX_VARIANTS_PRIMARY_AXIS = 4;
    private const MAX_VARIANTS_SECONDARY_AXIS = 3;
    private const LIST_PRICE_SHARE_PERCENT = 30;

    /** Images per product, so the detail page has a gallery to click through. */
    private const IMAGES_PER_PRODUCT = 3;

    public function __construct(
        private readonly DeterministicValueGenerator $values,
        private readonly DemoIdGenerator $ids,
        private readonly ProductReviewBuilder $reviews
    ) {
    }

    /**
     * @param array{id: string, name: string, path: array<int, string>, ancestorIds: array<int, string>, spec: array<string, mixed>} $leaf
     * @param array<string, array{id: string, options: array<string, string>}> $propertyGroups
     * @param array<int, string> $tagIds
     * @param array<int, string> $productMediaIds
     *
     * @return array<string, mixed>
     */
    public function build(
        string $channelKey,
        string $salesChannelId,
        array $leaf,
        string $productName,
        int $index,
        string $manufacturerId,
        string $manufacturerName,
        string $taxId,
        float $taxRate,
        array $propertyGroups,
        array $tagIds,
        array $productMediaIds,
        ChannelPricing $pricing
    ): array {
        /** @var array<string, mixed> $spec */
        $spec = $leaf['spec'];
        $key = $this->ids->key('product', $channelKey, implode('/', $leaf['path']), $productName);
        $productId = Uuid::fromStringToHex($key);

        $properties = $this->resolveProperties($key, $spec, $propertyGroups);
        $variants = $this->resolveVariants($key, $spec, $propertyGroups);
        $gross = $this->grossPrice($key, $spec);

        $productNumber = $this->productNumber($channelKey, $leaf, $index);

        $payload = [
            'id' => $productId,
            'productNumber' => $productNumber,
            'name' => $productName,
            'description' => $this->description($productName, $manufacturerName, $leaf['name'], $spec, $properties),
            'metaTitle' => \sprintf('%s | %s', $productName, $manufacturerName),
            'metaDescription' => $this->metaDescription($productName, $manufacturerName, $leaf['name']),
            'keywords' => $this->keywords($productName, $manufacturerName, $leaf, $properties),
            'active' => true,
            'stock' => $this->values->int($key . '|stock', 12, 640),
            'restockTime' => $this->values->int($key . '|restock', 1, 14),
            'minPurchase' => 1,
            'maxPurchase' => $this->values->int($key . '|max-purchase', 5, 50),
            'shippingFree' => $this->values->bool($key . '|shipping-free', 15),
            'markAsTopseller' => $this->values->bool($key . '|topseller', 12),
            'manufacturerId' => $manufacturerId,
            'manufacturerNumber' => $this->manufacturerNumber($key),
            'ean' => $this->ean($key),
            'taxId' => $taxId,
            'releaseDate' => $this->releaseDate($key),
            // Show one entry per product in listings rather than one per
            // variant, which is what a 4-colour backpack otherwise looks like.
            'variantListingConfig' => ['displayParent' => true, 'mainVariantId' => null],
            'price' => [$this->price($key, $gross, $taxRate)],
            'purchasePrices' => [$this->purchasePrice($key, $gross, $taxRate)],
            'prices' => $this->advancedPrices($key, $productId, $gross, $taxRate, $pricing),
            'categories' => $this->toIdList($leaf['ancestorIds']),
            'properties' => $this->toIdList($this->allPropertyOptionIds($properties, $variants)),
            'tags' => $this->toIdList($this->values->pickMany($key . '|tags', $tagIds, $this->values->int($key . '|tag-count', 1, 3))),
            'visibilities' => [[
                'id' => Uuid::fromStringToHex($key . '|visibility|' . $salesChannelId),
                'salesChannelId' => $salesChannelId,
                'visibility' => 30,
            ]],
            'customFields' => [
                DemoDataConstants::FIELD_SOURCE_KEY => $key,
                DemoDataConstants::FIELD_GENERATED => true,
                DemoDataConstants::FIELD_CHANNEL_KEY => $channelKey,
            ],
            ...$this->physicalAttributes($key),
        ];

        // Reviews hang off the parent: that is where the storefront shows them,
        // and a rating split across four colour variants averages to nothing.
        $reviews = $this->reviews->build($key, $productId, $salesChannelId);

        if ($reviews !== []) {
            $payload['productReviews'] = $reviews;
        }

        // The leaf's own themed photographs when the manifest supplied them,
        // falling back to the bundled pool when it could not be fetched.
        $pool = $this->photoPool($spec, $productMediaIds);
        $gallery = $this->gallery($key, $index, $leaf['path'], $pool);

        if ($gallery !== null) {
            $payload['media'] = $gallery['media'];
            $payload['coverId'] = $gallery['coverId'];
        }

        if ($variants !== []) {
            // Deterministic ids here too: without one Shopware mints a fresh
            // row per write, and a re-write collides on (product, option).
            $payload['configuratorSettings'] = array_values(array_map(
                static fn (string $optionId): array => [
                    'id' => Uuid::fromStringToHex($key . '|configurator|' . $optionId),
                    'optionId' => $optionId,
                ],
                $this->variantOptionIds($variants)
            ));
            $payload['children'] = $this->children($key, $productId, $productName, $productNumber, $variants, $gross, $taxRate, $pricing);
        }

        return $payload;
    }

    /**
     * Descriptive properties: one option per declared group, so a product reads
     * as a coherent spec sheet rather than a random bag of attributes.
     *
     * @param array<string, mixed> $spec
     * @param array<string, array{id: string, options: array<string, string>}> $propertyGroups
     *
     * @return array<string, array{name: string, optionId: string, optionName: string}> group key => chosen option
     */
    private function resolveProperties(string $key, array $spec, array $propertyGroups): array
    {
        /** @var array<int, string> $groupKeys */
        $groupKeys = \is_array($spec['properties'] ?? null) ? $spec['properties'] : [];
        $chosen = [];

        foreach ($groupKeys as $groupKey) {
            $group = $propertyGroups[$groupKey] ?? null;

            if ($group === null || $group['options'] === []) {
                continue;
            }

            // PHP turns numeric array keys into ints, so "39" comes back as 39
            // from a shoe-size group. Everything downstream wants a label.
            $optionName = (string)$this->values->pick($key . '|property|' . $groupKey, array_keys($group['options']));

            $chosen[$groupKey] = [
                'name' => $groupKey,
                'optionId' => $group['options'][$optionName],
                'optionName' => $optionName,
            ];
        }

        return $chosen;
    }

    /**
     * Variant axes, for the share of products that are configurable.
     *
     * @param array<string, mixed> $spec
     * @param array<string, array{id: string, options: array<string, string>}> $propertyGroups
     *
     * @return array<int, array<int, array{id: string, name: string}>> one entry per axis
     */
    private function resolveVariants(string $key, array $spec, array $propertyGroups): array
    {
        /** @var array<int, string> $axes */
        $axes = \is_array($spec['variantAxis'] ?? null) ? $spec['variantAxis'] : [];

        if ($axes === [] || !$this->values->bool($key . '|has-variants', self::VARIANT_SHARE_PERCENT)) {
            return [];
        }

        $resolved = [];
        $limits = [self::MAX_VARIANTS_PRIMARY_AXIS, self::MAX_VARIANTS_SECONDARY_AXIS];

        foreach (array_slice($axes, 0, 2) as $position => $groupKey) {
            $group = $propertyGroups[$groupKey] ?? null;

            if ($group === null || \count($group['options']) < 2) {
                continue;
            }

            $names = $this->values->pickMany(
                $key . '|axis|' . $groupKey,
                array_keys($group['options']),
                $this->values->int($key . '|axis-size|' . $groupKey, 2, $limits[$position] ?? 2)
            );

            $resolved[] = array_map(
                static fn (int|string $name): array => ['id' => $group['options'][$name], 'name' => (string)$name],
                $names
            );
        }

        return $resolved;
    }

    /**
     * @param array<int, array<int, array{id: string, name: string}>> $variants
     *
     * @return array<int, string>
     */
    private function variantOptionIds(array $variants): array
    {
        $ids = [];

        foreach ($variants as $axis) {
            foreach ($axis as $option) {
                $ids[] = $option['id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Shopware needs every variant option to sit on the parent's property list
     * as well, otherwise the configurator has nothing to filter against.
     *
     * @param array<string, array{name: string, optionId: string, optionName: string}> $properties
     * @param array<int, array<int, array{id: string, name: string}>> $variants
     *
     * @return array<int, string>
     */
    private function allPropertyOptionIds(array $properties, array $variants): array
    {
        return array_values(array_unique([
            ...array_column($properties, 'optionId'),
            ...$this->variantOptionIds($variants),
        ]));
    }

    /**
     * @param array<int, array<int, array{id: string, name: string}>> $variants
     *
     * @return array<int, array<string, mixed>>
     */
    private function children(
        string $key,
        string $parentId,
        string $parentName,
        string $parentNumber,
        array $variants,
        float $parentGross,
        float $taxRate,
        ChannelPricing $pricing
    ): array {
        $children = [];
        $position = 0;

        foreach ($this->combinations($variants) as $combination) {
            ++$position;
            $childKey = $key . '|variant|' . implode('+', array_column($combination, 'name'));
            $childProductId = Uuid::fromStringToHex($childKey);
            $suffix = implode(' / ', array_column($combination, 'name'));

            // Variants move the parent price by a small, stable amount so the
            // listing shows a believable "from" price instead of one flat number.
            $delta = $this->values->float($childKey . '|delta', -0.08, 0.22, 4);
            $gross = round($parentGross * (1 + $delta), 2);

            $children[] = [
                'id' => $childProductId,
                'parentId' => $parentId,
                'productNumber' => \sprintf('%s-%02d', $parentNumber, $position),
                'name' => \sprintf('%s — %s', $parentName, $suffix),
                'stock' => $this->values->int($childKey . '|stock', 0, 220),
                'ean' => $this->ean($childKey),
                'manufacturerNumber' => $this->manufacturerNumber($childKey),
                'price' => [$this->price($childKey, $gross, $taxRate)],
                'purchasePrices' => [$this->purchasePrice($childKey, $gross, $taxRate)],
                // Variants price independently, so their quantity breaks do too
                // — otherwise the 2 TB model inherits the 128 GB model's tiers.
                'prices' => $this->advancedPrices($childKey, $childProductId, $gross, $taxRate, $pricing),
                'options' => $this->toIdList(array_column($combination, 'id')),
                'customFields' => [
                    DemoDataConstants::FIELD_SOURCE_KEY => $childKey,
                    DemoDataConstants::FIELD_GENERATED => true,
                ],
                ...$this->physicalAttributes($childKey),
            ];
        }

        return $children;
    }

    /**
     * Cartesian product of the variant axes.
     *
     * @param array<int, array<int, array{id: string, name: string}>> $variants
     *
     * @return array<int, array<int, array{id: string, name: string}>>
     */
    private function combinations(array $variants): array
    {
        $result = [[]];

        foreach ($variants as $axis) {
            $next = [];

            foreach ($result as $prefix) {
                foreach ($axis as $option) {
                    $next[] = [...$prefix, $option];
                }
            }

            $result = $next;
        }

        return $result === [[]] ? [] : $result;
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function grossPrice(string $key, array $spec): float
    {
        /** @var array<int, float> $range */
        $range = \is_array($spec['price'] ?? null) ? $spec['price'] : [9.99, 99.99];
        $gross = $this->values->float($key . '|price', (float)($range[0] ?? 9.99), (float)($range[1] ?? 99.99), 2);

        // Charm pricing — a catalogue of prices ending in .37 reads as generated.
        return floor($gross) + 0.99;
    }

    /**
     * @return array<string, mixed>
     */
    private function price(string $key, float $gross, float $taxRate): array
    {
        $price = [
            'currencyId' => Defaults::CURRENCY,
            'gross' => $gross,
            'net' => round($gross / (1 + ($taxRate / 100)), 2),
            'linked' => true,
        ];

        if ($this->values->bool($key . '|list-price', self::LIST_PRICE_SHARE_PERCENT)) {
            $listGross = floor($gross * $this->values->float($key . '|list-factor', 1.15, 1.4, 2)) + 0.99;
            $price['listPrice'] = [
                'currencyId' => Defaults::CURRENCY,
                'gross' => $listGross,
                'net' => round($listGross / (1 + ($taxRate / 100)), 2),
                'linked' => true,
            ];
        }

        return $price;
    }

    /**
     * The channel's quantity breaks for one product.
     *
     * Every row is keyed on the product and the tier's lower bound, so a
     * re-run rewrites the same rows rather than stacking a second price list
     * on top of the first — which Shopware would happily let us do, and which
     * shows up in the storefront as overlapping quantity ranges.
     *
     * @return array<int, array<string, mixed>>
     */
    private function advancedPrices(
        string $key,
        string $productId,
        float $gross,
        float $taxRate,
        ChannelPricing $pricing
    ): array {
        $prices = [];

        foreach ($pricing->tiers as [$from, $to, $discount]) {
            $tierGross = round($gross * $pricing->factor * (1 - $discount), 2);

            $prices[] = [
                'id' => Uuid::fromStringToHex($key . '|price|' . $pricing->ruleId . '|' . $from),
                'productId' => $productId,
                'productVersionId' => Defaults::LIVE_VERSION,
                'ruleId' => $pricing->ruleId,
                'quantityStart' => $from,
                'quantityEnd' => $to,
                'price' => [[
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => $tierGross,
                    'net' => round($tierGross / (1 + ($taxRate / 100)), 2),
                    'linked' => true,
                ]],
            ];
        }

        return $prices;
    }

    /**
     * @return array<string, mixed>
     */
    private function purchasePrice(string $key, float $gross, float $taxRate): array
    {
        $purchaseGross = round($gross * $this->values->float($key . '|margin', 0.5, 0.75, 3), 2);

        return [
            'currencyId' => Defaults::CURRENCY,
            'gross' => $purchaseGross,
            'net' => round($purchaseGross / (1 + ($taxRate / 100)), 2),
            'linked' => true,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function physicalAttributes(string $key): array
    {
        return [
            'weight' => $this->values->float($key . '|weight', 0.08, 24.0, 2),
            'width' => $this->values->float($key . '|width', 4.0, 120.0, 1),
            'height' => $this->values->float($key . '|height', 2.0, 90.0, 1),
            'length' => $this->values->float($key . '|length', 6.0, 150.0, 1),
        ];
    }

    /**
     * @param array<string, mixed> $spec
     * @param array<int, string> $fallback
     *
     * @return array<int, string>
     */
    private function photoPool(array $spec, array $fallback): array
    {
        $own = $spec['photoMediaIds'] ?? null;

        return \is_array($own) && $own !== [] ? array_values(array_filter($own, '\is_string')) : $fallback;
    }

    /**
     * Assigns images round-robin rather than by hash.
     *
     * Picking at random from a small pool clusters: some photographs end up on
     * three times as many products as others, which is what makes a small pool
     * look smaller than it is. Walking the pool in order spreads it flat — with
     * a dozen source photographs across two thousand products, flat is the most
     * that can be done.
     *
     * It does not prevent two identical covers appearing next to each other in
     * a listing: listings sort by name, price or whatever the shopper picked,
     * not by the order products were seeded in. Only more source imagery fixes
     * that.
     *
     * Each category starts at its own offset, so the same image is not the
     * first thing on every listing page in the shop.
     *
     * @param array<int, string> $path the leaf's key path, used only for the per-category offset
     * @param array<int, string> $productMediaIds
     *
     * @return array{media: array<int, array{id: string, mediaId: string, position: int}>, coverId: string}|null
     */
    private function gallery(string $key, int $index, array $path, array $productMediaIds): ?array
    {
        $poolSize = \count($productMediaIds);

        if ($poolSize === 0) {
            return null;
        }

        $offset = $this->values->int(implode('/', $path) . '|media-offset', 0, $poolSize - 1);
        $media = [];

        for ($slot = 0; $slot < min(self::IMAGES_PER_PRODUCT, $poolSize); ++$slot) {
            $mediaId = $productMediaIds[($offset + $index - 1 + $slot) % $poolSize];

            $media[] = [
                'id' => Uuid::fromStringToHex($key . '|media|' . $slot),
                'mediaId' => $mediaId,
                'position' => $slot + 1,
            ];
        }

        return ['media' => $media, 'coverId' => $media[0]['id']];
    }

    /**
     * @param array{id: string, name: string, path: array<int, string>, ancestorIds: array<int, string>, spec: array<string, mixed>} $leaf
     */
    private function productNumber(string $channelKey, array $leaf, int $index): string
    {
        $channel = strtoupper(substr(preg_replace('/[^a-z]/i', '', $channelKey) ?: 'GEN', 0, 3));
        $category = strtoupper(substr(preg_replace('/[^a-z]/i', '', $leaf['name']) ?: 'CAT', 0, 4));
        // Three hex characters of the category path keep numbers unique when two
        // sales channels carry categories with the same first four letters.
        $discriminator = strtoupper(substr(hash('sha256', implode('/', $leaf['path'])), 0, 3));

        return \sprintf('KMH-%s-%s%s-%03d', $channel, $category, $discriminator, $index);
    }

    /**
     * @param array<string, mixed> $spec
     * @param array<string, array{name: string, optionId: string, optionName: string}> $properties
     */
    private function description(
        string $productName,
        string $manufacturerName,
        string $categoryName,
        array $spec,
        array $properties
    ): string {
        /** @var array<int, string> $features */
        $features = \is_array($spec['features'] ?? null) ? $spec['features'] : [];

        $bullets = array_map(
            static fn (string $feature): string => '<li>' . htmlspecialchars($feature, \ENT_QUOTES) . '</li>',
            $features
        );

        foreach ($properties as $property) {
            $bullets[] = \sprintf(
                '<li>%s: %s</li>',
                htmlspecialchars(ucwords(str_replace('-', ' ', $property['name'])), \ENT_QUOTES),
                htmlspecialchars($property['optionName'], \ENT_QUOTES)
            );
        }

        return \sprintf(
            '<p>The <strong>%s</strong> from %s is built to be used every day and repaired rather than replaced — '
            . 'our pick of the %s range.</p><ul>%s</ul>',
            htmlspecialchars($productName, \ENT_QUOTES),
            htmlspecialchars($manufacturerName, \ENT_QUOTES),
            htmlspecialchars(strtolower($categoryName), \ENT_QUOTES),
            implode('', $bullets)
        );
    }

    private function metaDescription(string $productName, string $manufacturerName, string $categoryName): string
    {
        return mb_substr(\sprintf(
            '%s by %s. Part of our %s range — specifications, pricing and availability.',
            $productName,
            $manufacturerName,
            strtolower($categoryName)
        ), 0, 160);
    }

    /**
     * @param array{id: string, name: string, path: array<int, string>, ancestorIds: array<int, string>, spec: array<string, mixed>} $leaf
     * @param array<string, array{name: string, optionId: string, optionName: string}> $properties
     */
    private function keywords(string $productName, string $manufacturerName, array $leaf, array $properties): string
    {
        $keywords = [
            $productName,
            $manufacturerName,
            $leaf['name'],
            ...array_column($properties, 'optionName'),
        ];

        return mb_substr(implode(', ', array_unique($keywords)), 0, 255);
    }

    private function manufacturerNumber(string $key): string
    {
        return 'MPN-' . strtoupper(substr(hash('sha256', $key . '|mpn'), 0, 8));
    }

    /**
     * A 13-digit number that looks like an EAN. Deliberately not a real GTIN —
     * demo data must never collide with a registered article number.
     */
    private function ean(string $key): string
    {
        return '499' . str_pad((string)($this->values->int($key . '|ean', 0, 9999999999) % 10000000000), 10, '0', \STR_PAD_LEFT);
    }

    private function releaseDate(string $key): string
    {
        $daysAgo = $this->values->int($key . '|release', 5, 720);

        return (new \DateTimeImmutable('today'))
            ->modify(\sprintf('-%d days', $daysAgo))
            ->format(\DATE_ATOM);
    }

    /**
     * @param array<int, string> $ids
     *
     * @return array<int, array{id: string}>
     */
    private function toIdList(array $ids): array
    {
        return array_values(array_map(
            static fn (string $id): array => ['id' => $id],
            array_unique($ids)
        ));
    }
}
