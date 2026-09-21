<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Seeder;

use Kommandhub\DemoData\Exception\DemoDataException;
use Kommandhub\DemoData\Service\EntityResolver;
use Kommandhub\DemoData\Service\SeedReport;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * Resolves each blueprint sales channel to a real one, and each sales channel to
 * a root category.
 *
 * Three rules, in order:
 *
 *  1. A channel this plugin created before is reused.
 *  2. A channel whose name matches, or whose name is on the blueprint's `adopt`
 *     list, is taken over — that is how a stock install's "Storefront" becomes
 *     the flagship channel instead of gaining a near-identical twin.
 *  3. Only then is a channel created, cloning language / currency / country /
 *     payment / shipping from an existing storefront so the new channel is
 *     actually usable rather than a half-configured stub.
 *
 * An adopted channel keeps the navigation category it already has. Repointing a
 * live channel's root would move every product the merchant has under it, which
 * is precisely the destructive behaviour this generator must not have.
 */
class SalesChannelSeeder
{
    /**
     * A navigation root is a shop front, not a shelf. Shopware's own stock root
     * carries the landing-page layout; a root with no layout falls through to
     * the default *category* layout and renders as a bare product listing with
     * a filter sidebar.
     */
    private const ROOT_LAYOUT_TYPE = 'landingpage';

    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param EntityRepository<SalesChannelDomainCollection> $salesChannelDomainRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $salesChannelDomainRepository,
        private readonly CategoryWriter $categories,
        private readonly EntityResolver $resolver
    ) {
    }

    /**
     * @param array{name: string, rootCategory: string, domain: string, adopt: array<int, string>} $definition
     *
     * @return array{id: string, rootCategoryId: string, created: bool, rootOwned: bool} rootOwned says whether this plugin may lay the root out
     */
    public function resolveChannel(
        Context $context,
        SeedReport $report,
        string $channelKey,
        array $definition
    ): array {
        $deterministicId = $this->resolver->ids()->id('sales-channel', $channelKey);
        $names = array_merge([$definition['name']], $definition['adopt']);

        $resolved = $this->resolver->resolve(
            $this->salesChannelRepository,
            $context,
            $deterministicId,
            [new EqualsAnyFilter('name', $names)]
        );

        if ($resolved->exists) {
            $resolved->adopted ? $report->adopted('sales_channel') : $report->reused('sales_channel');

            /** @var SalesChannelEntity|null $channel */
            $channel = $resolved->entity;
            $rootId = $channel?->getNavigationCategoryId();

            if ($rootId !== null) {
                $report->reused('root_category');

                // Ownership is a property of the *root*, not of the channel. A
                // channel adopted on an earlier run still has the root this
                // plugin minted for it, and that root is ours to lay out — so
                // the test is whether the id is one we would have generated.
                $rootOwned = $rootId === $this->resolver->ids()->id('category', $channelKey);

                if (!$rootOwned) {
                    // The merchant's root. A generated landing page is offered
                    // but not imposed; this only rescues a root with no layout
                    // at all, which would render as a product listing.
                    $this->categories->fillLayoutIfEmpty($context, $report, 'root_category', $rootId, self::ROOT_LAYOUT_TYPE);
                }

                return ['id' => $resolved->id, 'rootCategoryId' => $rootId, 'created' => false, 'rootOwned' => $rootOwned];
            }

            // A channel without a navigation category is broken rather than
            // merchant-owned, so giving it one is a repair, not an overwrite.
            $rootId = $this->createRootCategory($context, $report, $channelKey, $definition['rootCategory']);
            $this->salesChannelRepository->update(
                [['id' => $resolved->id, 'navigationCategoryId' => $rootId]],
                $context
            );

            return ['id' => $resolved->id, 'rootCategoryId' => $rootId, 'created' => false, 'rootOwned' => true];
        }

        $rootId = $this->createRootCategory($context, $report, $channelKey, $definition['rootCategory']);
        $this->createChannel($context, $report, $deterministicId, $definition, $rootId);

        return ['id' => $deterministicId, 'rootCategoryId' => $rootId, 'created' => true, 'rootOwned' => true];
    }

    /**
     * Every sales channel a shopper could actually open, newest domain wins.
     *
     * Channels without an http(s) domain are left out rather than listed with a
     * dead link — a Headless channel's "default.headless0" is an API identifier,
     * not somewhere to send a visitor.
     *
     * @return array<int, array{id: string, name: string, url: string}>
     */
    public function browsableChannels(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('domains');
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $channels = [];
        $found = $this->salesChannelRepository->search($criteria, $context)->getEntities();

        foreach ($found as $channel) {
            /** @var SalesChannelEntity $channel */
            $url = null;

            foreach ($channel->getDomains() ?? [] as $domain) {
                if (str_starts_with($domain->getUrl(), 'http://') || str_starts_with($domain->getUrl(), 'https://')) {
                    $url = $domain->getUrl();

                    break;
                }
            }

            if ($url === null) {
                continue;
            }

            $channels[] = [
                'id' => $channel->getId(),
                'name' => $channel->getName() ?? 'Store',
                'url' => $url,
            ];
        }

        return $channels;
    }

    /**
     * Points every sales channel at the shared footer and service trees.
     *
     * Only channels that have none are touched. A merchant who wired their own
     * footer menu keeps it — this fills a gap, it does not impose a layout.
     */
    public function assignFooterAndService(
        Context $context,
        SeedReport $report,
        string $footerCategoryId,
        string $serviceCategoryId
    ): void {
        $updates = [];
        $found = $this->salesChannelRepository->search(new Criteria(), $context)->getEntities();

        foreach ($found as $channel) {
            /** @var SalesChannelEntity $channel */
            $update = [];

            if ($channel->getFooterCategoryId() === null) {
                $update['footerCategoryId'] = $footerCategoryId;
            }

            if ($channel->getServiceCategoryId() === null) {
                $update['serviceCategoryId'] = $serviceCategoryId;
            }

            if ($update === []) {
                $report->reused('sales_channel_footer');

                continue;
            }

            $updates[] = ['id' => $channel->getId()] + $update;
            $report->enriched('sales_channel_footer');
        }

        if ($updates !== []) {
            $this->salesChannelRepository->update($updates, $context);
        }
    }

    private function createRootCategory(
        Context $context,
        SeedReport $report,
        string $channelKey,
        string $name
    ): string {
        // No cmsPageId here on purpose: a root this plugin created gets the
        // channel's own generated landing page once the products exist, and
        // setting a stock layout first would only be overwritten.
        return $this->categories->upsert(
            $context,
            $report,
            'root_category',
            [$channelKey],
            null,
            $name,
            [
                'active' => true,
                'visible' => true,
                'type' => CategoryDefinition::TYPE_PAGE,
                'displayNestedProducts' => true,
            ]
        );
    }

    /**
     * @param array{name: string, rootCategory: string, domain: string, adopt: array<int, string>} $definition
     */
    private function createChannel(
        Context $context,
        SeedReport $report,
        string $channelId,
        array $definition,
        string $rootCategoryId
    ): void {
        $template = $this->fetchTemplateChannel($context);

        $payload = [
            'id' => $channelId,
            'name' => $definition['name'],
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'navigationCategoryId' => $rootCategoryId,
            'active' => true,
            'languageId' => $template['languageId'],
            'currencyId' => $template['currencyId'],
            'paymentMethodId' => $template['paymentMethodId'],
            'shippingMethodId' => $template['shippingMethodId'],
            'countryId' => $template['countryId'],
            'customerGroupId' => $template['customerGroupId'],
            'languages' => $this->toIdList($template['languages']),
            'currencies' => $this->toIdList($template['currencies']),
            'paymentMethods' => $this->toIdList($template['paymentMethods']),
            'shippingMethods' => $this->toIdList($template['shippingMethods']),
            'countries' => $this->toIdList($template['countries']),
        ];

        if ($template['snippetSetId'] !== null && !$this->domainTaken($context, $definition['domain'])) {
            $payload['domains'] = [[
                'id' => $this->resolver->ids()->id('sales-channel-domain', $channelId),
                'url' => $definition['domain'],
                'languageId' => $template['languageId'],
                'currencyId' => $template['currencyId'],
                'snippetSetId' => $template['snippetSetId'],
            ]];
        } else {
            $report->note(\sprintf(
                'Sales channel "%s" created without a domain (%s is already in use or no snippet set was found).',
                $definition['name'],
                $definition['domain']
            ));
        }

        $this->salesChannelRepository->create([$payload], $context);
        $report->created('sales_channel');

        // A new channel has no theme until one is assigned and compiled, and
        // that lives in shopware/storefront. Depending on the Storefront bundle
        // just to run a theme command would be a heavy dependency for a data
        // seeder, so the operator is told instead of guessed at.
        $report->note(\sprintf(
            'Sales channel "%s" was created without a theme. Run "bin/console theme:change --all --sync Storefront" '
            . 'to assign and compile one, or the storefront renders unstyled.',
            $definition['name']
        ));
    }

    /**
     * @return array{languageId: string, currencyId: string, paymentMethodId: string, shippingMethodId: string, countryId: string, customerGroupId: string, snippetSetId: string|null, languages: array<int, string>, currencies: array<int, string>, paymentMethods: array<int, string>, shippingMethods: array<int, string>, countries: array<int, string>}
     */
    private function fetchTemplateChannel(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $criteria->addAssociation('languages');
        $criteria->addAssociation('currencies');
        $criteria->addAssociation('paymentMethods');
        $criteria->addAssociation('shippingMethods');
        $criteria->addAssociation('countries');
        $criteria->addAssociation('domains');
        $criteria->setLimit(1);

        /** @var SalesChannelEntity|null $template */
        $template = $this->salesChannelRepository->search($criteria, $context)->getEntities()->first();

        if ($template === null) {
            throw new DemoDataException(
                'No storefront sales channel found to copy language, currency and payment configuration from. '
                . 'Create one storefront channel first, then re-run the generator.'
            );
        }

        return [
            'languageId' => $template->getLanguageId(),
            'currencyId' => $template->getCurrencyId(),
            'paymentMethodId' => $template->getPaymentMethodId(),
            'shippingMethodId' => $template->getShippingMethodId(),
            'countryId' => $template->getCountryId(),
            'customerGroupId' => $template->getCustomerGroupId(),
            'snippetSetId' => $template->getDomains()?->first()?->getSnippetSetId(),
            'languages' => $template->getLanguages()?->getIds() ?? [$template->getLanguageId()],
            'currencies' => $template->getCurrencies()?->getIds() ?? [$template->getCurrencyId()],
            'paymentMethods' => $template->getPaymentMethods()?->getIds() ?? [$template->getPaymentMethodId()],
            'shippingMethods' => $template->getShippingMethods()?->getIds() ?? [$template->getShippingMethodId()],
            'countries' => $template->getCountries()?->getIds() ?? [$template->getCountryId()],
        ];
    }

    private function domainTaken(Context $context, string $url): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('url', rtrim($url, '/')));

        return $this->salesChannelDomainRepository->searchIds($criteria, $context)->getTotal() > 0;
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
