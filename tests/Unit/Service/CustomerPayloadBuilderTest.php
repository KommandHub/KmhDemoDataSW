<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Service;

use Kommandhub\DemoData\Blueprint\PeopleBlueprint;
use Kommandhub\DemoData\Service\CustomerPayloadBuilder;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\DeterministicValueGenerator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;

class CustomerPayloadBuilderTest extends TestCase
{
    private CustomerPayloadBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new CustomerPayloadBuilder(
            new DeterministicValueGenerator(),
            new DemoIdGenerator()
        );
    }

    /**
     * Customer number and email are both unique in Shopware, and a duplicate
     * does not fail its own row — it fails the entire write.
     */
    public function testCustomerNumbersAndEmailsAreUniqueAcrossChannels(): void
    {
        $numbers = [];
        $emails = [];
        $ids = [];

        foreach (['flagship', 'fresh', 'trade', 'wellness', 'outlet'] as $channelKey) {
            for ($i = 1; $i <= 40; ++$i) {
                $customer = $this->build($channelKey, $i);
                $numbers[] = $customer['customerNumber'];
                $emails[] = $customer['email'];
                $ids[] = $customer['id'];
            }
        }

        $this->assertSame($numbers, array_unique($numbers));
        $this->assertSame($emails, array_unique($emails));
        $this->assertSame($ids, array_unique($ids));
    }

    public function testEveryCustomerHasAUsableAddressBook(): void
    {
        for ($i = 1; $i <= 40; ++$i) {
            $customer = $this->build('fresh', $i);
            /** @var array<int, array<string, mixed>> $addresses */
            $addresses = $customer['addresses'];
            $addressIds = array_column($addresses, 'id');

            $this->assertNotEmpty($addresses);
            $this->assertContains($customer['defaultBillingAddressId'], $addressIds);
            $this->assertContains($customer['defaultShippingAddressId'], $addressIds);

            foreach ($addresses as $address) {
                $this->assertNotSame('', $address['street']);
                $this->assertNotSame('', $address['city']);
                $this->assertNotSame('', $address['zipcode']);
                $this->assertSame('country-id', $address['countryId']);
            }
        }
    }

    public function testSomeCustomersShipSomewhereElse(): void
    {
        $separate = 0;

        for ($i = 1; $i <= 40; ++$i) {
            $customer = $this->build('fresh', $i);

            if ($customer['defaultShippingAddressId'] !== $customer['defaultBillingAddressId']) {
                ++$separate;
                $this->assertCount(2, $customer['addresses']);
            }
        }

        $this->assertGreaterThan(0, $separate);
        $this->assertLessThan(40, $separate);
    }

    public function testBusinessAccountsCarryACompany(): void
    {
        $business = $this->build('trade', 1, true);
        $private = $this->build('fresh', 1);

        $this->assertSame(CustomerEntity::ACCOUNT_TYPE_BUSINESS, $business['accountType']);
        $this->assertNotEmpty($business['company']);

        $this->assertSame(CustomerEntity::ACCOUNT_TYPE_PRIVATE, $private['accountType']);
        $this->assertArrayNotHasKey('company', $private);
    }

    public function testOnlyTheRequestedAccountsCarryAPassword(): void
    {
        $this->assertSame(PeopleBlueprint::DEMO_PASSWORD, $this->build('fresh', 1, false, true)['password']);
        $this->assertArrayNotHasKey('password', $this->build('fresh', 2));
    }

    public function testEveryEmailIsUndeliverable(): void
    {
        // A demo shop that can send a real person an order confirmation is a
        // demo shop that will, eventually.
        for ($i = 1; $i <= 20; ++$i) {
            $this->assertStringEndsWith('@example.com', (string)$this->build('outlet', $i)['email']);
        }
    }

    public function testCustomersAreStableAcrossRuns(): void
    {
        $this->assertEquals($this->build('fresh', 5), $this->build('fresh', 5));
    }

    public function testLoginEmailMatchesTheAccountItNames(): void
    {
        $this->assertSame(
            $this->build('wellness', 1, false, true)['email'],
            $this->builder->loginEmail('wellness', 1)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function build(string $channelKey, int $index, bool $business = false, bool $withPassword = false): array
    {
        return $this->builder->build(
            $channelKey,
            $index,
            [
                'salesChannelId' => 'sales-channel-id',
                'languageId' => 'language-id',
                'countryId' => 'country-id',
                'paymentMethodId' => 'payment-method-id',
            ],
            'customer-group-id',
            'salutation-id',
            $business,
            $withPassword
        );
    }
}
