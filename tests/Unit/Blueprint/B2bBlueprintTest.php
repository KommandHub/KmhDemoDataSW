<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Blueprint;

use Kommandhub\DemoData\Blueprint\B2bBlueprint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(B2bBlueprint::class)]
class B2bBlueprintTest extends TestCase
{
    public function testTwoActiveCompaniesAndOnePendingOne(): void
    {
        $statuses = array_column(B2bBlueprint::companies(), 'status');

        static::assertSame(['active', 'active', 'pending'], $statuses);
    }

    public function testEveryCompanyHasOneAdminAndThreeEmployees(): void
    {
        foreach (B2bBlueprint::companies() as $key => $company) {
            $admins = array_filter($company['members'], static fn (array $member): bool => $member['admin']);

            static::assertCount(4, $company['members'], $key);
            static::assertCount(1, $admins, $key);
            static::assertContains(B2bBlueprint::ROLE_PURCHASING, array_column($company['members'], 'role'), $key);
        }
    }

    public function testActiveCompaniesShareTwoListsOwnedByTheirMembers(): void
    {
        foreach (B2bBlueprint::companies() as $key => $company) {
            static::assertCount($company['status'] === 'active' ? 2 : 0, $company['lists'], $key);

            foreach ($company['lists'] as $list) {
                static::assertArrayHasKey($list['owner'], $company['members'], $key);
            }
        }
    }

    public function testLoginsAreUniqueReservedAddresses(): void
    {
        $emails = [];

        foreach (B2bBlueprint::companies() as $key => $company) {
            foreach ($company['members'] as $member) {
                $emails[] = B2bBlueprint::email($key, $member['firstName'], $member['lastName']);
            }
        }

        static::assertSame('amina.okafor@northwind.example.com', $emails[0]);
        static::assertSame($emails, array_unique($emails));
    }
}
