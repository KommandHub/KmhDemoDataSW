<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Service;

use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\DeterministicValueGenerator;
use Kommandhub\DemoData\Service\ProductPayloadBuilder;
use Kommandhub\DemoData\Service\ProductReviewBuilder;
use PHPUnit\Framework\TestCase;

class ProductPayloadBuilderEdgeCasesTest extends TestCase
{
    public function testResolvePropertiesSkipsUnknownOrEmptyGroups(): void
    {
        $builder = new ProductPayloadBuilder(new DeterministicValueGenerator(), new DemoIdGenerator(), new ProductReviewBuilder(new DeterministicValueGenerator()));
        $method = (new \ReflectionClass($builder))->getMethod('resolveProperties');

        $resolved = $method->invoke($builder, 'key', ['properties' => ['colour', 'size']], [
            'colour' => ['id' => 'colour-id', 'options' => []],
        ]);

        $this->assertSame([], $resolved);
    }

    public function testResolveVariantsSkipsAxesWithoutEnoughOptions(): void
    {
        $values = new DeterministicValueGenerator();
        $key = null;
        for ($i = 0; $i < 500; ++$i) {
            $candidate = 'variants-' . $i;

            if ($values->bool($candidate . '|has-variants', 40)) {
                $key = $candidate;
                break;
            }
        }

        self::assertNotNull($key);

        $builder = new ProductPayloadBuilder($values, new DemoIdGenerator(), new ProductReviewBuilder(new DeterministicValueGenerator()));
        $method = (new \ReflectionClass($builder))->getMethod('resolveVariants');

        $resolved = $method->invoke($builder, $key, ['variantAxis' => ['colour', 'size']], [
            'colour' => ['id' => 'colour-id', 'options' => ['Black' => 'opt-black']],
            'size' => ['id' => 'size-id', 'options' => []],
        ]);

        $this->assertSame([], $resolved);
    }
}
