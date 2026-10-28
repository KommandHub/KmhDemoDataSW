<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Blueprint;

/**
 * The B2B side of the trade channel: companies, their people and the lists
 * they share, for shops running the KommandHub B2B suite.
 *
 * Plain data, like the other blueprints. Seeded only when the B2B Foundation
 * is active (shopping lists only with B2B Quick Order as well), so a shop
 * without the suite never sees any of it.
 */
final class B2bBlueprint
{
    /** The channel the companies trade in. Only it has business accounts. */
    public const CHANNEL_KEY = 'trade';

    public const ROLE_BUYER = 'buyer';
    public const ROLE_PURCHASING = 'purchasing';

    /** Historic orders per active company, spread across its members. */
    public const ORDERS_PER_COMPANY = 3;

    /**
     * The default role every company has, as the Foundation creates it on
     * registration: the permissions flagged "default for Buyer".
     */
    public const BUYER_ROLE_NAME = 'Buyer';

    /**
     * Everything except running the company's people and roles, which stays
     * with the company admin.
     */
    public const PURCHASING_ROLE_NAME = 'Purchasing manager';

    /** @var array<int, string> */
    public const PURCHASING_WITHHELD = ['company.role.manage', 'company.employee.manage'];

    /**
     * Two active companies to show the suite with, and one pending company for
     * the administration's approval demo. A pending company's members can log
     * in but not order, so it has no order history and no lists.
     *
     * Members: `admin` is the company admin; `role` is the role they hold.
     * Lists are shared with the company; `owner` is a member key.
     *
     * @return array<string, array{
     *     name: string,
     *     status: 'active'|'pending',
     *     registrationNumber: string,
     *     taxId: string,
     *     street: string,
     *     zipcode: string,
     *     city: string,
     *     members: array<string, array{firstName: string, lastName: string, admin: bool, role: string}>,
     *     lists: array<string, array{name: string, owner: string, items: int}>
     * }>
     */
    public static function companies(): array
    {
        return [
            'northwind' => [
                'name' => 'Northwind Building Supplies Ltd',
                'status' => 'active',
                'registrationNumber' => 'HRB 104233',
                'taxId' => 'DE298765431',
                'street' => 'Hafenstraße 14',
                'zipcode' => '20457',
                'city' => 'Hamburg',
                'members' => [
                    'admin' => ['firstName' => 'Amina', 'lastName' => 'Okafor', 'admin' => true, 'role' => self::ROLE_BUYER],
                    'purchasing' => ['firstName' => 'Jonas', 'lastName' => 'Weber', 'admin' => false, 'role' => self::ROLE_PURCHASING],
                    'buyer-1' => ['firstName' => 'Clara', 'lastName' => 'Hoffmann', 'admin' => false, 'role' => self::ROLE_BUYER],
                    'buyer-2' => ['firstName' => 'Emeka', 'lastName' => 'Nwosu', 'admin' => false, 'role' => self::ROLE_BUYER],
                ],
                'lists' => [
                    'site-restock' => ['name' => 'Site restock', 'owner' => 'purchasing', 'items' => 5],
                    'workshop' => ['name' => 'Workshop consumables', 'owner' => 'buyer-1', 'items' => 4],
                ],
            ],
            'brightwater' => [
                'name' => 'Brightwater Facilities GmbH',
                'status' => 'active',
                'registrationNumber' => 'HRB 87120',
                'taxId' => 'DE314159265',
                'street' => 'Rheinallee 61',
                'zipcode' => '40545',
                'city' => 'Düsseldorf',
                'members' => [
                    'admin' => ['firstName' => 'Stefan', 'lastName' => 'Brandt', 'admin' => true, 'role' => self::ROLE_BUYER],
                    'purchasing' => ['firstName' => 'Priya', 'lastName' => 'Raman', 'admin' => false, 'role' => self::ROLE_PURCHASING],
                    'buyer-1' => ['firstName' => 'Lukas', 'lastName' => 'Meier', 'admin' => false, 'role' => self::ROLE_BUYER],
                    'buyer-2' => ['firstName' => 'Fatima', 'lastName' => 'Haddad', 'admin' => false, 'role' => self::ROLE_BUYER],
                ],
                'lists' => [
                    'monthly' => ['name' => 'Monthly facilities order', 'owner' => 'purchasing', 'items' => 5],
                    'cleaning' => ['name' => 'Cleaning and hygiene', 'owner' => 'buyer-2', 'items' => 4],
                ],
            ],
            'harbourline' => [
                'name' => 'Harbourline Joinery Co.',
                'status' => 'pending',
                'registrationNumber' => 'HRB 55102',
                'taxId' => 'DE271828182',
                'street' => 'Speicherweg 3',
                'zipcode' => '28195',
                'city' => 'Bremen',
                'members' => [
                    'admin' => ['firstName' => 'Kwame', 'lastName' => 'Mensah', 'admin' => true, 'role' => self::ROLE_BUYER],
                    'purchasing' => ['firstName' => 'Ingrid', 'lastName' => 'Larsen', 'admin' => false, 'role' => self::ROLE_PURCHASING],
                    'buyer-1' => ['firstName' => 'Tomas', 'lastName' => 'Novak', 'admin' => false, 'role' => self::ROLE_BUYER],
                    'buyer-2' => ['firstName' => 'Elif', 'lastName' => 'Demir', 'admin' => false, 'role' => self::ROLE_BUYER],
                ],
                'lists' => [],
            ],
        ];
    }

    /**
     * example.com is reserved for documentation, so no real inbox ever gets
     * an invitation or order mail from the demo shop.
     */
    public static function email(string $companyKey, string $firstName, string $lastName): string
    {
        return strtolower(\sprintf('%s.%s@%s.example.com', $firstName, $lastName, $companyKey));
    }
}
