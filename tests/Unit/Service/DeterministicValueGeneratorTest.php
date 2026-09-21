<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Service;

use Kommandhub\DemoData\Service\DeterministicValueGenerator;
use PHPUnit\Framework\TestCase;

class DeterministicValueGeneratorTest extends TestCase
{
    private DeterministicValueGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new DeterministicValueGenerator();
    }

    public function testIntIsStableForTheSameKey(): void
    {
        $first = $this->generator->int('product|stock', 1, 1000);
        $second = $this->generator->int('product|stock', 1, 1000);

        $this->assertSame($first, $second);
    }

    public function testIntStaysWithinBounds(): void
    {
        for ($i = 0; $i < 200; ++$i) {
            $value = $this->generator->int('key-' . $i, 5, 9);

            $this->assertGreaterThanOrEqual(5, $value);
            $this->assertLessThanOrEqual(9, $value);
        }
    }

    public function testIntCollapsesWhenBoundsAreInverted(): void
    {
        $this->assertSame(7, $this->generator->int('any', 7, 7));
        $this->assertSame(7, $this->generator->int('any', 7, 3));
    }

    public function testDifferentKeysProduceDifferentValues(): void
    {
        $values = [];

        for ($i = 0; $i < 50; ++$i) {
            $values[] = $this->generator->int('key-' . $i, 0, 1000000);
        }

        // Not a distribution test — just proof the key actually feeds the hash.
        $this->assertGreaterThan(40, \count(array_unique($values)));
    }

    public function testFloatIsStableAndBounded(): void
    {
        $value = $this->generator->float('weight', 0.5, 4.5, 2);

        $this->assertSame($value, $this->generator->float('weight', 0.5, 4.5, 2));
        $this->assertGreaterThanOrEqual(0.5, $value);
        $this->assertLessThanOrEqual(4.5, $value);
        $this->assertSame($value, round($value, 2));
    }

    public function testFloatCollapsesWhenBoundsAreInverted(): void
    {
        $this->assertSame(3.14, $this->generator->float('pi', 3.1415, 2.0, 2));
    }

    public function testBoolHonoursThePercentage(): void
    {
        $this->assertFalse($this->generator->bool('anything', 0));
        $this->assertTrue($this->generator->bool('anything', 100));
    }

    public function testPickReturnsAMemberOfTheList(): void
    {
        $values = ['a', 'b', 'c'];
        $picked = $this->generator->pick('key', $values);

        $this->assertContains($picked, $values);
        $this->assertSame($picked, $this->generator->pick('key', $values));
    }

    public function testPickRejectsAnEmptyList(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->generator->pick('key', []);
    }

    public function testPickManyReturnsDistinctValuesAndIsStable(): void
    {
        $values = ['a', 'b', 'c', 'd', 'e'];
        $picked = $this->generator->pickMany('key', $values, 3);

        $this->assertCount(3, $picked);
        $this->assertSame($picked, array_unique($picked));
        $this->assertSame($picked, $this->generator->pickMany('key', $values, 3));

        foreach ($picked as $value) {
            $this->assertContains($value, $values);
        }
    }

    public function testPickManyCapsAtTheListSize(): void
    {
        $this->assertCount(2, $this->generator->pickMany('key', ['a', 'b'], 10));
        $this->assertSame([], $this->generator->pickMany('key', ['a', 'b'], 0));
    }
}
