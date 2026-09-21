<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Service;

use Kommandhub\DemoData\Service\DeterministicValueGenerator;
use Kommandhub\DemoData\Service\ProductNameGenerator;
use PHPUnit\Framework\TestCase;

class ProductNameGeneratorTest extends TestCase
{
    private ProductNameGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ProductNameGenerator(new DeterministicValueGenerator());
    }

    public function testNamesPreferExplicitProductsAndTrimToTarget(): void
    {
        $names = $this->generator->names('leaf', ['products' => ['A', 'B', 'C']], 2);

        $this->assertSame(['A', 'B'], $names);
    }

    public function testNamesGenerateWhenExplicitListIsShort(): void
    {
        $names = $this->generator->names('leaf', [
            'series' => ['Halo'],
            'models' => ['One', 'Two'],
        ], 2);

        $this->assertCount(2, $names);
        $this->assertContains('Halo One', $names);
    }

    public function testNamesGeneratePairingsAndEditionFallbacks(): void
    {
        $names = $this->generator->names('leaf', [
            'products' => ['Aurora Over-Ear ANC'],
            'series' => ['Aurora'],
            'models' => ['Over-Ear ANC', 'Studio'],
            'editions' => ['Select'],
        ], 4);

        $this->assertSame('Aurora Over-Ear ANC', $names[0]);
        $this->assertContains('Aurora Studio', $names);
        $this->assertContains('Aurora Over-Ear ANC Select', $names);
    }

    public function testNamesReturnOnlyExplicitWhenNoGeneratorsAreAvailable(): void
    {
        $names = $this->generator->names('leaf', ['products' => ['Only'], 'series' => 'invalid'], 3);

        $this->assertSame(['Only'], $names);
    }

    public function testCapacityCountsExplicitPairingsAndEditionVariants(): void
    {
        $capacity = $this->generator->capacity([
            'products' => ['Handwritten'],
            'series' => ['Halo'],
            'models' => ['One', 'Two'],
            'editions' => ['Select', 'Pro'],
        ]);

        $this->assertSame(7, $capacity);
    }
}
