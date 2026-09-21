<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Service;

use Kommandhub\DemoData\Blueprint\DemoBlueprint;
use Kommandhub\DemoData\Blueprint\PeopleBlueprint;
use Kommandhub\DemoData\Seeder\CategorySeeder;
use Kommandhub\DemoData\Seeder\CrossSellingSeeder;
use Kommandhub\DemoData\Seeder\CustomerGroupSeeder;
use Kommandhub\DemoData\Seeder\CustomerSeeder;
use Kommandhub\DemoData\Seeder\OrderSeeder;
use Kommandhub\DemoData\Seeder\FooterSeeder;
use Kommandhub\DemoData\Seeder\LandingPageSeeder;
use Kommandhub\DemoData\Seeder\ManufacturerSeeder;
use Kommandhub\DemoData\Seeder\PriceRuleSeeder;
use Kommandhub\DemoData\Seeder\MediaSeeder;
use Kommandhub\DemoData\Seeder\ProductSeeder;
use Kommandhub\DemoData\Seeder\PropertySeeder;
use Kommandhub\DemoData\Seeder\ShopContextResolver;
use Kommandhub\DemoData\Seeder\SalesChannelSeeder;
use Kommandhub\DemoData\Seeder\TagSeeder;
use Kommandhub\DemoData\Seeder\TaxResolver;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;

/**
 * Runs the seeders in dependency order and reports what happened.
 *
 * Shared vocabulary first — manufacturers, properties, tags, media are the same
 * across every sales channel and are resolved once. Then each sales channel in
 * turn: resolve the channel, resolve its root category, build its tree, fill its
 * leaves with products.
 *
 * The order is the dependency graph and nothing else; anything that needs a
 * different order needs a different graph, not a flag.
 */
class DemoDataSeeder
{
    public function __construct(
        private readonly ManufacturerSeeder $manufacturerSeeder,
        private readonly PropertySeeder $propertySeeder,
        private readonly TagSeeder $tagSeeder,
        private readonly MediaSeeder $mediaSeeder,
        private readonly SalesChannelSeeder $salesChannelSeeder,
        private readonly CategorySeeder $categorySeeder,
        private readonly ProductSeeder $productSeeder,
        private readonly FooterSeeder $footerSeeder,
        private readonly CrossSellingSeeder $crossSellingSeeder,
        private readonly PriceRuleSeeder $priceRuleSeeder,
        private readonly LandingPageSeeder $landingPageSeeder,
        private readonly CustomerGroupSeeder $customerGroupSeeder,
        private readonly CustomerSeeder $customerSeeder,
        private readonly OrderSeeder $orderSeeder,
        private readonly ShopContextResolver $shop,
        private readonly TaxResolver $taxResolver,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<int, string> $onlyChannels restrict the run to these blueprint channel keys
     * @param int|null $perCategory products per leaf category; defaults to the blueprint's own figure
     * @param bool $withOrders seed customers and their order history
     */
    public function seed(
        Context $context,
        array $onlyChannels = [],
        bool $withMedia = true,
        ?int $perCategory = null,
        bool $withOrders = true
    ): SeedReport {
        $perCategory ??= DemoBlueprint::PRODUCTS_PER_CATEGORY;

        $report = new SeedReport();

        $manufacturerDefinitions = DemoBlueprint::manufacturers();
        $manufacturerIds = $this->manufacturerSeeder->seed($context, $report);
        $propertyGroups = $this->propertySeeder->seed($context, $report);
        $tagIds = $this->tagSeeder->seed($context, $report);

        $channels = DemoBlueprint::salesChannels();

        if ($onlyChannels !== []) {
            $channels = array_intersect_key($channels, array_flip($onlyChannels));
        }

        $mediaIds = $withMedia
            ? $this->mediaSeeder->seed($context, $report, $this->mediaPaths($channels))
            : [];

        $productMediaIds = array_values(array_intersect_key($mediaIds, array_flip(DemoBlueprint::productImages())));

        // The manifest's themed photographs, fetched once per installation.
        // Without them the catalogue still seeds; it just falls back to the
        // bundled imagery, which is what a shop with no outbound network gets.
        $photoMediaIds = $withMedia
            ? $this->mediaSeeder->seedRemote($context, $report, $this->manifestPhotos($channels))
            : [];

        foreach ($channels as $channelKey => $definition) {
            $this->logger->info('Seeding demo sales channel', ['channel' => $channelKey]);

            $channel = $this->salesChannelSeeder->resolveChannel($context, $report, $channelKey, $definition);
            $tax = $this->taxResolver->resolve($context, $report, $definition['taxRate']);

            // Advanced prices are scoped by rule, so each channel needs its own
            // "shopping in this channel" rule before its products can be priced.
            $pricing = ChannelPricing::fromBlueprint(
                $this->priceRuleSeeder->resolve($context, $report, $channelKey, $definition['name'], $channel['id']),
                $definition
            );

            $leaves = $this->categorySeeder->seed(
                $context,
                $report,
                $channelKey,
                $channel['rootCategoryId'],
                $definition['tree'],
                $mediaIds,
                $photoMediaIds
            );

            $written = $this->productSeeder->seed(
                $context,
                $report,
                $channelKey,
                $channel['id'],
                $leaves,
                $manufacturerIds,
                $manufacturerDefinitions,
                $propertyGroups,
                $tagIds,
                $productMediaIds,
                $tax['id'],
                $tax['rate'],
                $perCategory,
                $pricing
            );

            // Needs the product ids, so it cannot be part of the product
            // payload — a product cannot cross-sell siblings that do not exist
            // yet. Scoped per channel: cross-selling a shopper to a product
            // that is invisible in their storefront is a dead link.
            $this->crossSellingSeeder->seed($context, $report, $written);

            // Customers and orders last of all. An order references products,
            // a customer, a payment method and three state machine states, so
            // it is the one thing that cannot be written until everything else
            // in the channel is in place.
            if ($withOrders) {
                $this->seedPeople($context, $report, $channelKey, $definition, $channel['id'], $written, $tax['rate']);
            }

            // Also after the products: the landing page's slider points at real
            // product ids, so the page cannot be built before they exist.
            $this->landingPageSeeder->seed(
                $context,
                $report,
                $channelKey,
                $definition,
                $channel['rootCategoryId'],
                $channel['rootOwned'],
                $mediaIds,
                $written
            );
        }

        // Last, and only once: the footer and service menus are shared by every
        // channel, and the service menu lists the channels, so it cannot be
        // built before they exist.
        $footer = $this->footerSeeder->seed(
            $context,
            $report,
            $this->salesChannelSeeder->browsableChannels($context)
        );

        $this->salesChannelSeeder->assignFooterAndService(
            $context,
            $report,
            $footer['footerCategoryId'],
            $footer['serviceCategoryId']
        );

        return $report;
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<int, array{leaf: array<string, mixed>, productIds: array<int, string>}> $written
     */
    private function seedPeople(
        Context $context,
        SeedReport $report,
        string $channelKey,
        array $definition,
        string $salesChannelId,
        array $written,
        float $taxRate
    ): void {
        $channel = $this->shop->channel($context, $salesChannelId);

        $customerIds = $this->customerSeeder->seed(
            $context,
            $report,
            $channelKey,
            $channel,
            $this->customerGroupSeeder->resolve($context, $report, $channelKey, $channel['customerGroupId']),
            $this->shop->salutationId($context),
            PeopleBlueprint::CUSTOMERS_PER_CHANNEL
        );

        $productIds = [];

        foreach ($written as $group) {
            $productIds = [...$productIds, ...$group['productIds']];
        }

        $this->orderSeeder->seed(
            $context,
            $report,
            $channelKey,
            $channel,
            $productIds,
            $customerIds,
            $taxRate,
            PeopleBlueprint::ORDERS_PER_CHANNEL
        );

        $report->note(\sprintf(
            'Demo logins for "%s": %s (password %s)',
            \is_string($definition['name'] ?? null) ? $definition['name'] : $channelKey,
            implode(', ', $this->customerSeeder->loginEmails($channelKey)),
            PeopleBlueprint::DEMO_PASSWORD
        ));
    }

    /**
     * Every photo id the selected channels' categories reference.
     *
     * @param array<string, array{tree: array<int, array<string, mixed>>}> $channels
     *
     * @return array<int, string>
     */
    private function manifestPhotos(array $channels): array
    {
        $photos = [];

        foreach ($channels as $definition) {
            $this->collectPhotos($definition['tree'], $photos);
        }

        return array_values(array_unique($photos));
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param array<int, string> $photos
     */
    private function collectPhotos(array $nodes, array &$photos): void
    {
        foreach ($nodes as $node) {
            if (\is_array($node['photos'] ?? null)) {
                foreach ($node['photos'] as $photo) {
                    if (\is_string($photo)) {
                        $photos[] = $photo;
                    }
                }
            }

            if (\is_array($node['children'] ?? null)) {
                /** @var array<int, array<string, mixed>> $children */
                $children = $node['children'];
                $this->collectPhotos($children, $photos);
            }
        }
    }

    /**
     * Product images plus the category images the selected channels actually
     * reference — importing the whole bundled set when only one channel is being
     * seeded would be pointless work.
     *
     * @param array<string, array{tree: array<int, array<string, mixed>>}> $channels
     *
     * @return array<int, string>
     */
    private function mediaPaths(array $channels): array
    {
        $paths = DemoBlueprint::productImages();

        foreach ($channels as $definition) {
            foreach ($definition['tree'] as $node) {
                if (isset($node['image']) && \is_string($node['image'])) {
                    $paths[] = $node['image'];
                }
            }
        }

        return array_values(array_unique($paths));
    }
}
