<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Seeder;

use Kommandhub\DemoData\Blueprint\B2bBlueprint;
use Kommandhub\DemoData\Seeder\B2bSeeder;
use Kommandhub\DemoData\Seeder\OrderSeeder;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\DeterministicValueGenerator;
use Kommandhub\DemoData\Service\SeedReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

#[CoversClass(B2bSeeder::class)]
#[UsesClass(B2bBlueprint::class)]
#[UsesClass(DemoIdGenerator::class)]
#[UsesClass(DeterministicValueGenerator::class)]
#[UsesClass(SeedReport::class)]
class B2bSeederTest extends TestCase
{
    private const CHANNEL = [
        'salesChannelId' => 'trade-channel',
        'languageId' => 'lang',
        'currencyId' => 'eur',
        'countryId' => 'de',
        'paymentMethodId' => 'pay',
        'shippingMethodId' => 'ship',
    ];

    /** @var array<string, list<array<string, mixed>>> entity → rows written */
    private array $written = [];

    /** @var array<string, list<string>> entity → ids that already exist */
    private array $existing = [];

    /** @var array<string, mixed> field → value the product search filtered on */
    private array $productFilters = [];

    public function testDoesNothingWithoutTheB2bFoundation(): void
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(false);
        $registry->expects($this->never())->method('getRepository');
        $orderSeeder = $this->createMock(OrderSeeder::class);
        $orderSeeder->expects($this->never())->method('seed');
        $report = new SeedReport();

        $seeder = new B2bSeeder($registry, $orderSeeder, new DemoIdGenerator(), new DeterministicValueGenerator(), []);
        $seeder->seed(Context::createDefaultContext(), $report, self::CHANNEL, 'trade-group', 'mr', 19.0);

        static::assertFalse($seeder->available());
        static::assertSame([], $report->toTable());
        static::assertSame([], $report->notes());
    }

    public function testSeedsCompaniesRolesMembersOrdersAndSharedLists(): void
    {
        $orderSeeder = $this->createMock(OrderSeeder::class);
        $orderSeeder->expects($this->exactly(2))->method('seed')
            ->willReturnCallback(static function (Context $context, SeedReport $report, string $channelKey, array $channel, array $productIds, array $customerIds): array {
                static::assertEqualsCanonicalizing(['p1', 'p2', 'p3', 'p4', 'p5', 'p6'], $productIds);
                static::assertCount(4, $customerIds);

                return ['order-' . $channelKey => $customerIds[1]];
            });
        $report = new SeedReport();

        $this->seeder($orderSeeder, true)->seed(Context::createDefaultContext(), $report, self::CHANNEL, 'trade-group', 'mr', 19.0);

        $ids = new DemoIdGenerator();
        static::assertCount(12, $this->written['customer']);
        static::assertCount(3, $this->written[B2bSeeder::COMPANY]);
        static::assertCount(6, $this->written[B2bSeeder::ROLE]);
        static::assertCount(12, $this->written[B2bSeeder::MEMBER]);
        static::assertCount(2, $this->written[B2bSeeder::ORDER_COMPANY]);
        static::assertCount(4, $this->written[B2bSeeder::SHOPPING_LIST]);

        $companies = array_column($this->written[B2bSeeder::COMPANY], null, 'name');
        static::assertSame('active', $companies['Northwind Building Supplies Ltd']['status']);
        static::assertSame('manual', $companies['Northwind Building Supplies Ltd']['verificationProvider']);
        static::assertSame('trade-group', $companies['Northwind Building Supplies Ltd']['customerGroupId']);
        static::assertSame('pending', $companies['Harbourline Joinery Co.']['status']);
        static::assertArrayNotHasKey('verifiedAt', $companies['Harbourline Joinery Co.']);

        $roles = array_filter($this->written[B2bSeeder::ROLE], static fn (array $role): bool => $role['companyId'] === $ids->id('b2b-company', 'northwind'));
        $roles = array_column($roles, null, 'name');
        static::assertSame(['quick_order.use', 'company.order.place'], $roles['Buyer']['permissions']);
        static::assertTrue($roles['Buyer']['isDefault']);
        static::assertSame(['quick_order.use', 'company.order.place', 'company.order.read_all'], $roles['Purchasing manager']['permissions']);
        static::assertFalse($roles['Purchasing manager']['isDefault']);

        $admins = array_filter($this->written[B2bSeeder::MEMBER], static fn (array $member): bool => $member['isAdmin']);
        static::assertCount(3, $admins);

        $customer = array_column($this->written['customer'], null, 'email')['jonas.weber@northwind.example.com'];
        static::assertSame('business', $customer['accountType']);
        static::assertSame('trade-group', $customer['groupId']);
        static::assertSame('Northwind Building Supplies Ltd', $customer['company']);
        static::assertSame('demo12345', $customer['password']);
        static::assertSame('KMH-B2B-NORTHWIND-PURCHASING', $customer['customerNumber']);

        $attribution = array_column($this->written[B2bSeeder::ORDER_COMPANY], null, 'orderId')['order-trade-b2b-northwind'];
        static::assertSame($ids->id('b2b-company', 'northwind'), $attribution['companyId']);
        static::assertSame($ids->id('b2b-customer', 'northwind', 'purchasing'), $attribution['memberCustomerId']);

        $list = array_column($this->written[B2bSeeder::SHOPPING_LIST], null, 'name')['Site restock'];
        static::assertTrue($list['shared']);
        static::assertSame('trade-channel', $list['salesChannelId']);
        static::assertSame($ids->id('b2b-customer', 'northwind', 'purchasing'), $list['customerId']);
        static::assertCount(5, $list['items']);
        static::assertSame(
            ['visibilities.salesChannelId' => 'trade-channel', 'childCount' => 0, 'active' => true],
            $this->productFilters
        );

        static::assertCount(3, $report->notes());
        static::assertStringContainsString('amina.okafor@northwind.example.com', $report->notes()[0]);
        static::assertStringContainsString('(pending)', $report->notes()[2]);
    }

    public function testRerunWritesNothingAndReportsReuse(): void
    {
        $orderSeeder = $this->createMock(OrderSeeder::class);
        $orderSeeder->method('seed')->willReturn([]);
        $this->existing = [
            'customer' => $this->memberIds('b2b-customer'),
            B2bSeeder::COMPANY => array_map(static fn (string $key): string => (new DemoIdGenerator())->id('b2b-company', $key), array_keys(B2bBlueprint::companies())),
        ];
        $report = new SeedReport();

        $this->seeder($orderSeeder, false)->seed(Context::createDefaultContext(), $report, self::CHANNEL, 'trade-group', 'mr', 19.0);

        static::assertArrayNotHasKey('customer', $this->written);
        static::assertArrayNotHasKey(B2bSeeder::COMPANY, $this->written);
        static::assertArrayNotHasKey(B2bSeeder::ORDER_COMPANY, $this->written);
        static::assertArrayNotHasKey(B2bSeeder::SHOPPING_LIST, $this->written);
        $table = array_column($report->toTable(), null, 0);
        static::assertSame('12', $table['customer'][2]);
        static::assertSame('3', $table[B2bSeeder::COMPANY][2]);
        static::assertSame('0', $table[B2bSeeder::COMPANY][1]);
    }

    public function testDescribesTheCompaniesAndWhichAlreadyExist(): void
    {
        $this->existing = [B2bSeeder::COMPANY => [(new DemoIdGenerator())->id('b2b-company', 'northwind')]];

        $state = $this->seeder($this->createMock(OrderSeeder::class), false)->describe(Context::createDefaultContext());

        static::assertTrue($state['available']);
        static::assertFalse($state['lists']);
        static::assertCount(3, $state['companies']);
        static::assertSame(['Northwind Building Supplies Ltd', 'active', true], [$state['companies'][0]['name'], $state['companies'][0]['status'], $state['companies'][0]['exists']]);
        static::assertFalse($state['companies'][2]['exists']);
        static::assertSame('pending', $state['companies'][2]['status']);
        static::assertSame(
            [
                ['email' => 'amina.okafor@northwind.example.com', 'name' => 'Amina Okafor', 'role' => 'admin'],
                ['email' => 'jonas.weber@northwind.example.com', 'name' => 'Jonas Weber', 'role' => 'purchasing'],
            ],
            \array_slice($state['companies'][0]['logins'], 0, 2)
        );
    }

    public function testDescribesNothingAsGeneratedWithoutTheB2bFoundation(): void
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(false);
        $registry->expects($this->never())->method('getRepository');

        $state = (new B2bSeeder($registry, $this->createMock(OrderSeeder::class), new DemoIdGenerator(), new DeterministicValueGenerator(), []))
            ->describe(Context::createDefaultContext());

        static::assertFalse($state['available']);
        static::assertFalse($state['lists']);
        static::assertSame([false, false, false], array_column($state['companies'], 'exists'));
    }

    public function testListsNeedQuickOrderAndOrderableProducts(): void
    {
        $orderSeeder = $this->createMock(OrderSeeder::class);
        $orderSeeder->method('seed')->willReturn([]);

        $this->seeder($orderSeeder, false)->seed(Context::createDefaultContext(), new SeedReport(), self::CHANNEL, 'trade-group', 'mr', 19.0);
        static::assertArrayNotHasKey(B2bSeeder::SHOPPING_LIST, $this->written);

        $this->written = [];
        $this->seeder($orderSeeder, true, [])->seed(Context::createDefaultContext(), new SeedReport(), self::CHANNEL, 'trade-group', 'mr', 19.0);
        static::assertArrayNotHasKey(B2bSeeder::SHOPPING_LIST, $this->written);
    }

    private function seeder(OrderSeeder&MockObject $orderSeeder, bool $quickOrder, ?array $orderable = null): B2bSeeder
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $entity): bool => $entity !== B2bSeeder::SHOPPING_LIST || $quickOrder);
        $registry->method('getRepository')->willReturnCallback(fn (string $entity): EntityRepository => $this->repository($entity, $orderable));

        return new B2bSeeder($registry, $orderSeeder, new DemoIdGenerator(), new DeterministicValueGenerator(), [
            new \stdClass(),
            new class() {
                public function getPermissions(): string
                {
                    return 'not a list';
                }
            },
            new class() {
                /** @return list<mixed> */
                public function getPermissions(): array
                {
                    $definition = static fn (string $key, bool $default): object => (object)['key' => $key, 'defaultForBuyer' => $default];

                    return [
                        $definition('quick_order.use', true),
                        $definition('company.order.place', true),
                        $definition('company.order.read_all', false),
                        $definition('company.role.manage', false),
                        $definition('company.employee.manage', false),
                        $definition('quick_order.use', true),
                        (object)['label' => 'no key'],
                        'not an object',
                    ];
                }
            },
        ]);
    }

    /**
     * @param list<string>|null $orderable product ids the product search finds
     */
    private function repository(string $entity, ?array $orderable): EntityRepository
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('searchIds')->willReturnCallback(function (Criteria $criteria) use ($entity, $orderable): IdSearchResult {
            if ($entity === 'product') {
                foreach ($criteria->getFilters() as $filter) {
                    \assert($filter instanceof EqualsFilter);
                    $this->productFilters[$filter->getField()] = $filter->getValue();
                }
            }

            $result = $this->createMock(IdSearchResult::class);
            $result->method('getIds')->willReturn($entity === 'product' ? ($orderable ?? ['p1', 'p2', 'p3', 'p4', 'p5', 'p6']) : ($this->existing[$entity] ?? []));

            return $result;
        });
        $repository->method('create')->willReturnCallback(function (array $rows) use ($entity): EntityWrittenContainerEvent {
            $this->written[$entity] = [...($this->written[$entity] ?? []), ...$rows];

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        return $repository;
    }

    /**
     * @return list<string>
     */
    private function memberIds(string $type): array
    {
        $ids = [];

        foreach (B2bBlueprint::companies() as $companyKey => $company) {
            foreach (array_keys($company['members']) as $memberKey) {
                $ids[] = (new DemoIdGenerator())->id($type, $companyKey, $memberKey);
            }
        }

        return $ids;
    }
}
