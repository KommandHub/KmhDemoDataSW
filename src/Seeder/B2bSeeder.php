<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Seeder;

use Kommandhub\DemoData\Blueprint\B2bBlueprint;
use Kommandhub\DemoData\Blueprint\PeopleBlueprint;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\DeterministicValueGenerator;
use Kommandhub\DemoData\Service\SeedReport;
use Kommandhub\DemoData\Util\DemoDataConstants;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * The trade channel's companies, for shops running the KommandHub B2B suite:
 * companies, their roles and members, shared shopping lists, and an order
 * history attributed to each company.
 *
 * The suite is optional and this plugin does not require it, so everything is
 * decided at runtime: nothing happens unless the B2B Foundation's entities are
 * registered (the plugin is active), and lists only with B2B Quick Order as
 * well. Writes go through those plugins' DAL entities — their public data
 * model — never SQL, so their own write validation still applies.
 *
 * Idempotent like every seeder here: deterministic ids, and only missing rows
 * are written.
 */
class B2bSeeder
{
    public const COMPANY = 'kmh_b2b_company';
    public const ROLE = 'kmh_b2b_role';
    public const MEMBER = 'kmh_b2b_company_member';
    public const ORDER_COMPANY = 'kmh_b2b_order_company';
    public const SHOPPING_LIST = 'kmh_b2b_shopping_list';

    /** Products sampled for shopping lists; only orderable ones are kept. */
    private const LIST_PRODUCT_SAMPLE = 60;

    /**
     * @param iterable<object> $permissionProviders the Foundation's permission providers
     *                                              (tag `kmh_b2b.permission_provider`); empty without the suite
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly OrderSeeder $orderSeeder,
        private readonly DemoIdGenerator $ids,
        private readonly DeterministicValueGenerator $values,
        private readonly iterable $permissionProviders
    ) {
    }

    public function available(): bool
    {
        return $this->registry->has(self::COMPANY);
    }

    /**
     * @param array{salesChannelId: string, languageId: string, currencyId: string, countryId: string, paymentMethodId: string, shippingMethodId: string} $channel
     */
    public function seed(
        Context $context,
        SeedReport $report,
        array $channel,
        string $customerGroupId,
        string $salutationId,
        float $taxRate
    ): void {
        if (!$this->available()) {
            return;
        }

        [$defaults, $all] = $this->permissions();
        $companies = B2bBlueprint::companies();
        $customers = $companyRows = $roles = $members = [];

        foreach ($companies as $companyKey => $company) {
            $companyId = $this->ids->id('b2b-company', $companyKey);
            $companyRows[$companyId] = $this->company($companyId, $company, $customerGroupId);
            $roleIds = [
                B2bBlueprint::ROLE_BUYER => $this->ids->id('b2b-role', $companyKey, B2bBlueprint::ROLE_BUYER),
                B2bBlueprint::ROLE_PURCHASING => $this->ids->id('b2b-role', $companyKey, B2bBlueprint::ROLE_PURCHASING),
            ];
            $roles[$roleIds[B2bBlueprint::ROLE_BUYER]] = ['id' => $roleIds[B2bBlueprint::ROLE_BUYER], 'companyId' => $companyId, 'name' => B2bBlueprint::BUYER_ROLE_NAME, 'permissions' => $defaults, 'isDefault' => true];
            $roles[$roleIds[B2bBlueprint::ROLE_PURCHASING]] = ['id' => $roleIds[B2bBlueprint::ROLE_PURCHASING], 'companyId' => $companyId, 'name' => B2bBlueprint::PURCHASING_ROLE_NAME, 'permissions' => array_values(array_diff($all, B2bBlueprint::PURCHASING_WITHHELD)), 'isDefault' => false];

            foreach ($company['members'] as $memberKey => $member) {
                $customerId = $this->customerId($companyKey, $memberKey);
                $customers[$customerId] = $this->customer($customerId, $companyKey, $memberKey, $company, $member, $channel, $customerGroupId, $salutationId);
                $memberId = $this->ids->id('b2b-member', $companyKey, $memberKey);
                $members[$memberId] = ['id' => $memberId, 'companyId' => $companyId, 'customerId' => $customerId, 'roleId' => $roleIds[$member['role']], 'isAdmin' => $member['admin'], 'status' => 'active'];
            }
        }

        // In dependency order: a member needs its customer, company and role.
        $this->createMissing($context, $report, 'customer', $customers);
        $this->createMissing($context, $report, self::COMPANY, $companyRows);
        $this->createMissing($context, $report, self::ROLE, $roles);
        $this->createMissing($context, $report, self::MEMBER, $members);

        $orderable = $this->orderableProducts($context, $channel['salesChannelId']);
        $this->seedOrders($context, $report, $companies, $channel, $orderable, $taxRate);
        $this->seedLists($context, $report, $companies, $channel['salesChannelId'], $orderable);

        foreach ($companies as $companyKey => $company) {
            $logins = [];

            foreach ($company['members'] as $member) {
                $logins[] = B2bBlueprint::email($companyKey, $member['firstName'], $member['lastName']);
            }

            $report->note(\sprintf('B2B logins for "%s" (%s): %s (password %s)', $company['name'], $company['status'], implode(', ', $logins), PeopleBlueprint::DEMO_PASSWORD));
        }
    }

    /**
     * What the administration shows before a run: whether the suite is there,
     * which demo companies already exist, and the logins they come with.
     *
     * @return array{available: bool, lists: bool, companies: list<array{name: string, status: string, exists: bool, logins: list<array{email: string, name: string, role: string}>}>}
     */
    public function describe(Context $context): array
    {
        $available = $this->available();
        $companies = B2bBlueprint::companies();
        $existing = $available
            ? $this->registry->getRepository(self::COMPANY)->searchIds(new Criteria(array_map(fn (string $key): string => $this->ids->id('b2b-company', $key), array_keys($companies))), $context)->getIds()
            : [];
        $rows = [];

        foreach ($companies as $companyKey => $company) {
            $logins = [];

            foreach ($company['members'] as $member) {
                $logins[] = [
                    'email' => B2bBlueprint::email($companyKey, $member['firstName'], $member['lastName']),
                    'name' => $member['firstName'] . ' ' . $member['lastName'],
                    'role' => $member['admin'] ? 'admin' : $member['role'],
                ];
            }

            $rows[] = [
                'name' => $company['name'],
                'status' => $company['status'],
                'exists' => \in_array($this->ids->id('b2b-company', $companyKey), $existing, true),
                'logins' => $logins,
            ];
        }

        return ['available' => $available, 'lists' => $this->registry->has(self::SHOPPING_LIST), 'companies' => $rows];
    }

    /**
     * Keys of the registered permissions: those the Foundation gives a new
     * company's "Buyer" role, and all of them.
     *
     * The providers are the Foundation's public `PermissionProviderInterface`,
     * read without importing it, since this plugin does not require the suite.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function permissions(): array
    {
        $defaults = $all = [];

        foreach ($this->permissionProviders as $provider) {
            if (!method_exists($provider, 'getPermissions')) {
                continue;
            }

            $definitions = $provider->getPermissions();

            foreach (\is_iterable($definitions) ? $definitions : [] as $definition) {
                if (!\is_object($definition) || !isset($definition->key) || !\is_string($definition->key)) {
                    continue;
                }

                $all[] = $definition->key;

                if (($definition->defaultForBuyer ?? false) === true) {
                    $defaults[] = $definition->key;
                }
            }
        }

        return [array_values(array_unique($defaults)), array_values(array_unique($all))];
    }

    /**
     * @param array{name: string, status: string, registrationNumber: string, taxId: string} $company
     *
     * @return array<string, mixed>
     */
    private function company(string $companyId, array $company, string $customerGroupId): array
    {
        $payload = [
            'id' => $companyId,
            'name' => $company['name'],
            'registrationNumber' => $company['registrationNumber'],
            'taxId' => $company['taxId'],
            'status' => $company['status'],
            'customerGroupId' => $customerGroupId,
        ];

        if ($company['status'] === 'active') {
            // As the administration's Approve action leaves it.
            $payload['verifiedAt'] = (new \DateTimeImmutable('today'))->modify('-200 days')->format(\DATE_ATOM);
            $payload['verificationProvider'] = 'manual';
        }

        return $payload;
    }

    /**
     * @param array{name: string, street: string, zipcode: string, city: string} $company
     * @param array{firstName: string, lastName: string} $member
     * @param array{salesChannelId: string, languageId: string, countryId: string, paymentMethodId: string} $channel
     *
     * @return array<string, mixed>
     */
    private function customer(
        string $customerId,
        string $companyKey,
        string $memberKey,
        array $company,
        array $member,
        array $channel,
        string $customerGroupId,
        string $salutationId
    ): array {
        $addressId = $this->ids->id('b2b-customer-address', $companyKey, $memberKey);
        $key = $this->ids->key('b2b-customer', $companyKey, $memberKey);

        return [
            'id' => $customerId,
            'customerNumber' => \sprintf('KMH-B2B-%s-%s', strtoupper($companyKey), strtoupper($memberKey)),
            'salutationId' => $salutationId,
            'firstName' => $member['firstName'],
            'lastName' => $member['lastName'],
            'email' => B2bBlueprint::email($companyKey, $member['firstName'], $member['lastName']),
            // The DAL hashes this on write; it is never stored as given.
            'password' => PeopleBlueprint::DEMO_PASSWORD,
            'groupId' => $customerGroupId,
            'salesChannelId' => $channel['salesChannelId'],
            'languageId' => $channel['languageId'],
            'defaultPaymentMethodId' => $channel['paymentMethodId'],
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'company' => $company['name'],
            'active' => true,
            'guest' => false,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [[
                'id' => $addressId,
                'customerId' => $customerId,
                'salutationId' => $salutationId,
                'firstName' => $member['firstName'],
                'lastName' => $member['lastName'],
                'company' => $company['name'],
                'street' => $company['street'],
                'zipcode' => $company['zipcode'],
                'city' => $company['city'],
                'countryId' => $channel['countryId'],
            ]],
            'customFields' => [
                DemoDataConstants::FIELD_SOURCE_KEY => $key,
                DemoDataConstants::FIELD_GENERATED => true,
                DemoDataConstants::FIELD_CHANNEL_KEY => B2bBlueprint::CHANNEL_KEY,
            ],
        ];
    }

    /**
     * Each active company's order history, from the shared order seeder, then
     * attributed to the company the way the Foundation does at checkout. A
     * pending company has not been able to order yet.
     *
     * @param array<string, array{name: string, status: string, members: array<string, mixed>}> $companies
     * @param array{salesChannelId: string, languageId: string, currencyId: string, countryId: string, paymentMethodId: string, shippingMethodId: string} $channel
     * @param array<int, string> $productIds
     */
    private function seedOrders(Context $context, SeedReport $report, array $companies, array $channel, array $productIds, float $taxRate): void
    {
        $attributions = [];

        foreach ($companies as $companyKey => $company) {
            if ($company['status'] !== 'active') {
                continue;
            }

            $memberIds = array_map(fn (string $memberKey): string => $this->customerId($companyKey, $memberKey), array_keys($company['members']));
            $orders = $this->orderSeeder->seed($context, $report, B2bBlueprint::CHANNEL_KEY . '-b2b-' . $companyKey, $channel, $productIds, $memberIds, $taxRate, B2bBlueprint::ORDERS_PER_COMPANY);

            foreach ($orders as $orderId => $customerId) {
                $id = $this->ids->id('b2b-order-company', $orderId);
                $attributions[$id] = [
                    'id' => $id,
                    'orderId' => $orderId,
                    'orderVersionId' => Defaults::LIVE_VERSION,
                    'companyId' => $this->ids->id('b2b-company', $companyKey),
                    'companyName' => $company['name'],
                    'memberCustomerId' => $customerId,
                ];
            }
        }

        $this->createMissing($context, $report, self::ORDER_COMPANY, $attributions);
    }

    /**
     * @param array<string, array{status: string, lists: array<string, array{name: string, owner: string, items: int}>}> $companies
     * @param array<int, string> $productIds
     */
    private function seedLists(Context $context, SeedReport $report, array $companies, string $salesChannelId, array $productIds): void
    {
        if (!$this->registry->has(self::SHOPPING_LIST) || $productIds === []) {
            return;
        }

        $lists = [];

        foreach ($companies as $companyKey => $company) {
            foreach ($company['lists'] as $listKey => $list) {
                $listId = $this->ids->id('b2b-shopping-list', $companyKey, $listKey);
                $key = $this->ids->key('b2b-shopping-list', $companyKey, $listKey);
                $items = [];

                foreach ($this->values->pickMany($key . '|products', $productIds, $list['items']) as $position => $productId) {
                    $items[] = [
                        'id' => $this->ids->id('b2b-shopping-list-item', $companyKey, $listKey, $productId),
                        'productId' => $productId,
                        'productVersionId' => Defaults::LIVE_VERSION,
                        'quantity' => $this->values->int($key . '|quantity|' . $productId, 1, 12),
                        'position' => $position,
                    ];
                }

                $lists[$listId] = [
                    'id' => $listId,
                    'customerId' => $this->customerId($companyKey, $list['owner']),
                    'companyId' => $this->ids->id('b2b-company', $companyKey),
                    'salesChannelId' => $salesChannelId,
                    'name' => $list['name'],
                    'shared' => true,
                    'items' => $items,
                ];
            }
        }

        $this->createMissing($context, $report, self::SHOPPING_LIST, $lists);
    }

    /**
     * Variant-free, active products the channel sells, so a list or an order
     * points at something that can go back into the cart. A parent with
     * variants would only ever be reported as not available.
     *
     * Read from the channel rather than handed in by the caller, so a B2B-only
     * run on an already seeded shop picks the same kind of products.
     *
     * @return array<int, string>
     */
    private function orderableProducts(Context $context, string $salesChannelId): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('visibilities.salesChannelId', $salesChannelId),
            new EqualsFilter('childCount', 0),
            new EqualsFilter('active', true)
        );

        /** @var list<string> $ids */
        $ids = $this->registry->getRepository('product')->searchIds($criteria, $context)->getIds();
        sort($ids);

        return $this->values->pickMany(B2bBlueprint::CHANNEL_KEY . '|b2b-products', $ids, self::LIST_PRODUCT_SAMPLE);
    }

    /**
     * @param array<string, array<string, mixed>> $payloads id → payload
     */
    private function createMissing(Context $context, SeedReport $report, string $entity, array $payloads): void
    {
        if ($payloads === []) {
            return;
        }

        $repository = $this->registry->getRepository($entity);
        $known = $repository->searchIds(new Criteria(array_keys($payloads)), $context)->getIds();
        $creates = array_values(array_diff_key($payloads, array_flip($known)));

        if (\count($payloads) > \count($creates)) {
            $report->reused($entity, \count($payloads) - \count($creates));
        }

        if ($creates !== []) {
            $report->created($entity, \count($creates));
            // A handful of rows per entity: one write, no chunking needed.
            $repository->create($creates, $context);
        }
    }

    private function customerId(string $companyKey, string $memberKey): string
    {
        return $this->ids->id('b2b-customer', $companyKey, $memberKey);
    }
}
