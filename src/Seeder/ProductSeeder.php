<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Seeder;

use Kommandhub\DemoData\Service\ChannelPricing;
use Kommandhub\DemoData\Service\BatchWriter;
use Kommandhub\DemoData\Service\EntityResolver;
use Kommandhub\DemoData\Service\ProductNameGenerator;
use Kommandhub\DemoData\Service\ProductPayloadBuilder;
use Kommandhub\DemoData\Service\ResolvedEntity;
use Kommandhub\DemoData\Service\SeedReport;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * Writes the products for one sales channel's category tree.
 *
 * Products are matched on their deterministic id first and on product number
 * second — the product number is Shopware's own unique key, so a catalogue
 * imported from an ERP that happens to use one of ours is enriched rather than
 * rejected by a duplicate-key error halfway through the run.
 *
 * Existing products are only ever topped up: blank descriptions, missing meta
 * data and missing category / visibility / property links get filled in, while
 * anything the merchant has already written stays as they wrote it.
 */
class ProductSeeder
{
    private const BATCH_SIZE = 40;

    /**
     * Association keys Shopware's writer treats as additive, so re-stating them
     * links what is missing without unlinking what is there.
     */
    private const ADDITIVE = ['categories', 'properties', 'tags', 'visibilities'];

    /**
     * Associations that are additive but must never be pushed onto a product
     * this plugin did not create. Fabricated reviews on a merchant's real
     * product are not a gap being filled — they are invented evidence, and an
     * advanced price list is worse still: it changes what customers are
     * charged.
     */
    private const OWNED_ONLY_ADDITIVE = ['productReviews', 'prices'];

    /**
     * Fields this plugin owns outright on products it created, corrected when
     * they drift. The cover is one: changing how images are distributed has to
     * reach the products already in the shop, or the improvement only ever
     * applies to a catalogue nobody has seeded yet.
     */
    private const OWNED_ONLY_OVERWRITE = ['coverId'];

    /**
     * Media is neither additive nor a scalar: the gallery rows keep their ids
     * while the photograph behind them changes, so an id-based diff reports
     * nothing missing and the new imagery never reaches the shop. The set is
     * compared by (row id, media id) and rewritten only when it actually
     * differs.
     */
    private const MEDIA_ASSOCIATION = 'media';

    private const FILL_IF_EMPTY = [
        'description',
        'metaTitle',
        'metaDescription',
        'keywords',
        'manufacturerId',
        'manufacturerNumber',
        'ean',
        'releaseDate',
        'variantListingConfig',
        'weight',
        'width',
        'height',
        'length',
        'customFields',
    ];

    /**
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly ProductPayloadBuilder $payloadBuilder,
        private readonly ProductNameGenerator $names,
        private readonly BatchWriter $batch,
        private readonly EntityResolver $resolver
    ) {
    }

    /**
     * @param array<int, array{id: string, name: string, path: array<int, string>, ancestorIds: array<int, string>, spec: array<string, mixed>}> $leaves
     * @param array<string, string> $manufacturerIds
     * @param array<string, array{id: string, options: array<string, string>}> $propertyGroups
     * @param array<string, array{name: string, link: string, description: string}> $manufacturerDefinitions
     * @param array<int, string> $tagIds
     * @param array<int, string> $productMediaIds
     *
     * @return array<int, array{leaf: array{id: string, name: string, path: array<int, string>, ancestorIds: array<int, string>, spec: array<string, mixed>}, productIds: array<int, string>}> only products this plugin owns
     */
    public function seed(
        Context $context,
        SeedReport $report,
        string $channelKey,
        string $salesChannelId,
        array $leaves,
        array $manufacturerIds,
        array $manufacturerDefinitions,
        array $propertyGroups,
        array $tagIds,
        array $productMediaIds,
        string $taxId,
        float $taxRate,
        int $perCategory,
        ChannelPricing $pricing
    ): array {
        $creates = [];
        $updates = [];
        $written = [];

        foreach ($leaves as $leaf) {
            $spec = $leaf['spec'];
            $productNames = $this->names->names(implode('/', $leaf['path']), $spec, $perCategory);
            $manufacturerKey = \is_string($spec['manufacturer'] ?? null) ? $spec['manufacturer'] : '';
            $manufacturerId = $manufacturerIds[$manufacturerKey] ?? null;

            if ($manufacturerId === null) {
                $report->skipped('product', \count($productNames));
                $report->note(\sprintf('Category "%s" names an unknown manufacturer and was skipped.', $leaf['name']));

                continue;
            }

            $index = 0;
            $ownedIds = [];

            foreach ($productNames as $productName) {
                $payload = $this->payloadBuilder->build(
                    $channelKey,
                    $salesChannelId,
                    $leaf,
                    $productName,
                    ++$index,
                    $manufacturerId,
                    $manufacturerDefinitions[$manufacturerKey]['name'] ?? $manufacturerKey,
                    $taxId,
                    $taxRate,
                    $propertyGroups,
                    $tagIds,
                    $productMediaIds,
                    $pricing
                );

                $ownedId = $this->collect($context, $report, $payload, $creates, $updates);

                if ($ownedId !== null) {
                    $ownedIds[] = $ownedId;
                }
            }

            if ($ownedIds !== []) {
                $written[] = ['leaf' => $leaf, 'productIds' => $ownedIds];
            }

            // Flush per category rather than per channel. At fifty products a
            // category, each with variants and a dozen reviews, holding a whole
            // channel's payloads in memory is how this runs out of it.
            $this->flush($context, $creates, $updates);
            $creates = [];
            $updates = [];
        }

        return $written;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $creates
     * @param array<int, array<string, mixed>> $updates
     *
     * @return string|null the product id when this plugin owns the row, null when it was adopted
     */
    private function collect(
        Context $context,
        SeedReport $report,
        array $payload,
        array &$creates,
        array &$updates
    ): ?string {
        /** @var string $productId */
        $productId = $payload['id'];
        /** @var string $productNumber */
        $productNumber = $payload['productNumber'];

        $resolved = $this->resolver->resolve(
            $this->productRepository,
            $context,
            $productId,
            [new EqualsFilter('productNumber', $productNumber)],
            // Loaded so the resolver can diff links instead of re-stating them.
            [...self::ADDITIVE, ...self::OWNED_ONLY_ADDITIVE, self::MEDIA_ASSOCIATION]
        );

        /** @var array<int, array<string, mixed>> $children */
        $children = \is_array($payload['children'] ?? null) ? $payload['children'] : [];

        /** @var array<int, array<string, mixed>> $reviews */
        $reviews = \is_array($payload['productReviews'] ?? null) ? $payload['productReviews'] : [];

        if (!$resolved->exists) {
            $creates[] = $payload;
            $report->created('product');
            $report->created('product_variant', \count($children));
            $report->created('product_review', \count($reviews));

            return $resolved->id;
        }

        if ($resolved->adopted) {
            $report->adopted('product');
            $report->skipped('product_review', \count($reviews));
            unset($payload['productReviews'], $payload['media'], $payload['coverId']);
        } else {
            $report->reused('product');
        }

        $additive = $resolved->adopted ? self::ADDITIVE : [...self::ADDITIVE, ...self::OWNED_ONLY_ADDITIVE];
        $overwrite = $resolved->adopted ? [] : self::OWNED_ONLY_OVERWRITE;
        $enrichment = $this->resolver->enrichmentPayload($resolved, $payload, self::FILL_IF_EMPTY, $additive, $overwrite);

        if (!$resolved->adopted && $this->galleryDiffers($resolved, $payload)) {
            $enrichment ??= ['id' => $resolved->id];
            $enrichment[self::MEDIA_ASSOCIATION] = $payload[self::MEDIA_ASSOCIATION];
            $report->enriched('product_media');
        }

        $addedReviews = 0;

        if ($enrichment !== null) {
            $updates[] = $enrichment;
            $report->enriched('product');
            $addedReviews = \is_array($enrichment['productReviews'] ?? null) ? \count($enrichment['productReviews']) : 0;
            $report->created('product_review', $addedReviews);
        }

        if (!$resolved->adopted) {
            $report->reused('product_review', \count($reviews) - $addedReviews);
        }

        // Variants are keyed on the parent, so an adopted parent would mint
        // children under somebody else's product. Only top up children we own.
        if ($children !== [] && !$resolved->adopted) {
            $this->collectChildren($context, $report, $children, $creates, $updates);
        } else {
            $report->reused('product_variant', \count($children));
        }

        return $resolved->adopted ? null : $resolved->id;
    }

    /**
     * Whether the product's stored gallery matches the one we would write.
     *
     * @param array<string, mixed> $payload
     */
    private function galleryDiffers(ResolvedEntity $resolved, array $payload): bool
    {
        if (!\is_array($payload[self::MEDIA_ASSOCIATION] ?? null)) {
            return false;
        }

        /** @var array<int, array<string, mixed>> $desired */
        $desired = $payload[self::MEDIA_ASSOCIATION];
        $current = $resolved->current(self::MEDIA_ASSOCIATION);

        if (!$current instanceof ProductMediaCollection) {
            return true;
        }

        $stored = [];

        foreach ($current as $row) {
            $stored[$row->getId()] = $row->getMediaId();
        }

        foreach ($desired as $row) {
            $id = $row['id'] ?? null;

            if (!\is_string($id) || ($stored[$id] ?? null) !== ($row['mediaId'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Variants are resolved as a batch rather than one at a time — a catalogue
     * with thousands of them cannot afford a query per variant.
     *
     * Existing variants are topped up like their parents: a variant that
     * predates a new field (its own quantity breaks, say) gets it on the next
     * run rather than staying silently incomplete.
     *
     * @param array<int, array<string, mixed>> $children
     * @param array<int, array<string, mixed>> $creates
     * @param array<int, array<string, mixed>> $updates
     */
    private function collectChildren(
        Context $context,
        SeedReport $report,
        array $children,
        array &$creates,
        array &$updates
    ): void {
        $ids = array_values(array_filter(
            array_column($children, 'id'),
            static fn (mixed $id): bool => \is_string($id)
        ));

        $criteria = new Criteria($ids);

        foreach (self::OWNED_ONLY_ADDITIVE as $association) {
            $criteria->addAssociation($association);
        }

        $known = [];
        $found = $this->productRepository->search($criteria, $context)->getEntities();

        foreach ($found as $entity) {
            $known[$entity->getId()] = $entity;
        }

        foreach ($children as $child) {
            $existing = \is_string($child['id']) ? ($known[$child['id']] ?? null) : null;

            if ($existing === null) {
                $creates[] = $child;
                $report->created('product_variant');

                continue;
            }

            $report->reused('product_variant');

            $enrichment = $this->resolver->enrichmentPayload(
                ResolvedEntity::owned($existing->getId(), $existing),
                $child,
                self::FILL_IF_EMPTY,
                self::OWNED_ONLY_ADDITIVE
            );

            if ($enrichment !== null) {
                $updates[] = $enrichment;
                $report->enriched('product_variant');
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $creates
     * @param array<int, array<string, mixed>> $updates
     */
    private function flush(Context $context, array $creates, array $updates): void
    {
        $this->batch->create($this->productRepository, $creates, $context, self::BATCH_SIZE);
        $this->batch->update($this->productRepository, $updates, $context, self::BATCH_SIZE);
    }
}
