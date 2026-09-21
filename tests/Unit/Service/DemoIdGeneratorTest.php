<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Service;

use Kommandhub\DemoData\Service\DemoIdGenerator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

class DemoIdGeneratorTest extends TestCase
{
    private DemoIdGenerator $ids;

    protected function setUp(): void
    {
        $this->ids = new DemoIdGenerator();
    }

    public function testIdIsAValidUuidHex(): void
    {
        $id = $this->ids->id('category', 'flagship', 'Footwear');

        $this->assertTrue(Uuid::isValid($id));
    }

    public function testIdIsStableAcrossCalls(): void
    {
        $this->assertSame(
            $this->ids->id('category', 'flagship', 'Footwear'),
            $this->ids->id('category', 'flagship', 'Footwear')
        );
    }

    /**
     * The bug this whole class exists to prevent: hashing a bare name meant the
     * same category name in two sales channels resolved to one shared row.
     */
    public function testSameNameInDifferentChannelsGetsDifferentIds(): void
    {
        $this->assertNotSame(
            $this->ids->id('category', 'flagship', 'Footwear'),
            $this->ids->id('category', 'trade', 'Footwear')
        );
    }

    public function testDifferentEntityTypesGetDifferentIds(): void
    {
        $this->assertNotSame(
            $this->ids->id('category', 'flagship'),
            $this->ids->id('sales-channel', 'flagship')
        );
    }

    public function testKeyIsNamespacedAndReadable(): void
    {
        $key = $this->ids->key('product', 'fresh', 'Beverages/Coffee & Tea', 'Morning Blend Coffee');

        $this->assertStringStartsWith(DemoIdGenerator::NAMESPACE . ':product:', $key);
        $this->assertStringContainsString('Morning Blend Coffee', $key);
    }

    public function testIdMatchesTheHashOfItsKey(): void
    {
        $this->assertSame(
            Uuid::fromStringToHex($this->ids->key('tag', 'Best Seller')),
            $this->ids->id('tag', 'Best Seller')
        );
    }
}
