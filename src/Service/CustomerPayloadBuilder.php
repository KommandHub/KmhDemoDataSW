<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Service;

use Kommandhub\DemoData\Blueprint\PeopleBlueprint;
use Kommandhub\DemoData\Util\DemoDataConstants;
use Shopware\Core\Checkout\Customer\CustomerEntity;

/**
 * Builds one demo customer and their address book.
 *
 * Pure, like the other builders: everything about a customer is derived from
 * their key, so the same customer comes out the same on every run and in every
 * installation.
 *
 * Only a couple of accounts per channel carry a password. Hashing is meant to
 * be slow, and hashing two hundred of them to demonstrate a login nobody will
 * use more than twice is time spent for nothing.
 */
class CustomerPayloadBuilder
{
    /** Share of customers with a shipping address that differs from billing. */
    private const SEPARATE_SHIPPING_PERCENT = 25;

    public function __construct(
        private readonly DeterministicValueGenerator $values,
        private readonly DemoIdGenerator $ids
    ) {
    }

    /**
     * @param array{salesChannelId: string, languageId: string, countryId: string, paymentMethodId: string} $channel
     *
     * @return array<string, mixed>
     */
    public function build(
        string $channelKey,
        int $index,
        array $channel,
        string $customerGroupId,
        string $salutationId,
        bool $business,
        bool $withPassword
    ): array {
        $key = $this->ids->key('customer', $channelKey, (string)$index);
        $customerId = $this->ids->id('customer', $channelKey, (string)$index);

        $firstName = $this->values->pick($key . '|first', PeopleBlueprint::firstNames());
        $lastName = $this->values->pick($key . '|last', PeopleBlueprint::lastNames());

        $billingId = $this->ids->id('customer-address', $channelKey, (string)$index, 'billing');
        $addresses = [$this->address($key . '|billing', $billingId, $customerId, $channel, $salutationId, $firstName, $lastName, $business)];
        $shippingId = $billingId;

        if ($this->values->bool($key . '|separate-shipping', self::SEPARATE_SHIPPING_PERCENT)) {
            $shippingId = $this->ids->id('customer-address', $channelKey, (string)$index, 'shipping');
            $addresses[] = $this->address($key . '|shipping', $shippingId, $customerId, $channel, $salutationId, $firstName, $lastName, false);
        }

        $payload = [
            'id' => $customerId,
            'customerNumber' => $this->customerNumber($channelKey, $index),
            'salutationId' => $salutationId,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $this->email($channelKey, $index, $firstName, $lastName),
            'groupId' => $customerGroupId,
            'salesChannelId' => $channel['salesChannelId'],
            'languageId' => $channel['languageId'],
            'defaultPaymentMethodId' => $channel['paymentMethodId'],
            'accountType' => $business ? CustomerEntity::ACCOUNT_TYPE_BUSINESS : CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'active' => true,
            'guest' => false,
            'defaultBillingAddressId' => $billingId,
            'defaultShippingAddressId' => $shippingId,
            'addresses' => $addresses,
            // Registered at some point before they could have ordered.
            'createdAt' => $this->registeredAt($key),
            'customFields' => [
                DemoDataConstants::FIELD_SOURCE_KEY => $key,
                DemoDataConstants::FIELD_GENERATED => true,
                DemoDataConstants::FIELD_CHANNEL_KEY => $channelKey,
            ],
        ];

        if ($business) {
            $payload['company'] = $this->values->pick($key . '|company', PeopleBlueprint::companies());
        }

        if ($withPassword) {
            // The DAL hashes this on write; it is never stored as given.
            $payload['password'] = PeopleBlueprint::DEMO_PASSWORD;
        }

        return $payload;
    }

    /**
     * The address of a customer who can be logged into, so whoever is being
     * shown the shop knows which account to use.
     */
    public function loginEmail(string $channelKey, int $index): string
    {
        $key = $this->ids->key('customer', $channelKey, (string)$index);
        $firstName = $this->values->pick($key . '|first', PeopleBlueprint::firstNames());
        $lastName = $this->values->pick($key . '|last', PeopleBlueprint::lastNames());

        return $this->email($channelKey, $index, $firstName, $lastName);
    }

    /**
     * @param array{countryId: string} $channel
     *
     * @return array<string, mixed>
     */
    private function address(
        string $key,
        string $addressId,
        string $customerId,
        array $channel,
        string $salutationId,
        string $firstName,
        string $lastName,
        bool $business
    ): array {
        $place = $this->values->pick($key . '|place', PeopleBlueprint::addresses());

        $payload = [
            'id' => $addressId,
            'customerId' => $customerId,
            'salutationId' => $salutationId,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'street' => $place['street'] . ' ' . $this->values->int($key . '|number', 1, 180),
            'zipcode' => $place['zip'],
            'city' => $place['city'],
            'countryId' => $channel['countryId'],
        ];

        if ($business) {
            $payload['company'] = $this->values->pick($key . '|company', PeopleBlueprint::companies());
        }

        return $payload;
    }

    /**
     * Namespaced by channel so two channels' customer lists never collide on a
     * number, which Shopware requires to be unique.
     */
    private function customerNumber(string $channelKey, int $index): string
    {
        return \sprintf('KMH-%s-%05d', strtoupper(substr($channelKey, 0, 3)), $index);
    }

    /**
     * example.com is reserved for documentation, so a demo shop can never send
     * a real person an order confirmation.
     */
    private function email(string $channelKey, int $index, string $firstName, string $lastName): string
    {
        $local = strtolower(
            preg_replace('/[^a-z]/i', '', $firstName) . '.' . preg_replace('/[^a-z]/i', '', $lastName)
        );

        return \sprintf('%s.%s%02d@example.com', $local, strtolower(substr($channelKey, 0, 3)), $index);
    }

    private function registeredAt(string $key): string
    {
        return (new \DateTimeImmutable('today'))
            ->modify(\sprintf('-%d days', $this->values->int($key . '|registered', 30, 900)))
            ->format(\DATE_ATOM);
    }
}
