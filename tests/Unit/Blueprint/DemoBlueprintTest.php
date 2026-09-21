<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Blueprint;

use Kommandhub\DemoData\Blueprint\DemoBlueprint;
use Kommandhub\DemoData\Service\DeterministicValueGenerator;
use Kommandhub\DemoData\Service\ProductNameGenerator;
use PHPUnit\Framework\TestCase;

/**
 * The blueprint is plain data, so the only thing worth testing is that it is
 * internally consistent — a category pointing at a manufacturer key that does
 * not exist silently drops a whole shelf of products at seed time, and nothing
 * in the type system catches it.
 */
class DemoBlueprintTest extends TestCase
{
    public function testEveryLeafReferencesAKnownManufacturer(): void
    {
        $manufacturers = array_keys(DemoBlueprint::manufacturers());

        foreach ($this->leaves() as $path => $leaf) {
            $this->assertArrayHasKey('manufacturer', $leaf, $path);
            $this->assertContains($leaf['manufacturer'], $manufacturers, $path);
        }
    }

    public function testEveryLeafReferencesKnownPropertyGroups(): void
    {
        $groups = array_keys(DemoBlueprint::propertyGroups());

        foreach ($this->leaves() as $path => $leaf) {
            foreach ([...$leaf['properties'] ?? [], ...$leaf['variantAxis'] ?? []] as $groupKey) {
                $this->assertContains($groupKey, $groups, $path);
            }
        }
    }

    public function testEveryVariantAxisHasEnoughOptionsToBeAnAxis(): void
    {
        $groups = DemoBlueprint::propertyGroups();

        foreach ($this->leaves() as $path => $leaf) {
            foreach ($leaf['variantAxis'] ?? [] as $groupKey) {
                $this->assertGreaterThan(1, \count($groups[$groupKey]['options']), $path);
            }
        }
    }

    /**
     * A leaf without name pools silently falls back to its handful of
     * hand-written products, so the catalogue quietly comes out hundreds short
     * with nothing reporting an error.
     */
    public function testEveryLeafCanGenerateAFullCatalogue(): void
    {
        $generator = new ProductNameGenerator(new DeterministicValueGenerator());
        $target = DemoBlueprint::PRODUCTS_PER_CATEGORY;

        foreach ($this->leaves() as $path => $leaf) {
            $this->assertGreaterThanOrEqual(
                $target,
                $generator->capacity($leaf),
                $path . ' cannot reach ' . $target . ' products'
            );
        }
    }

    /**
     * Every category carries the same number, so no corner of the shop is
     * conspicuously emptier than the rest.
     */
    public function testTheCatalogueIsSpreadEvenlyAcrossCategories(): void
    {
        $generator = new ProductNameGenerator(new DeterministicValueGenerator());
        $target = DemoBlueprint::PRODUCTS_PER_CATEGORY;
        $counts = [];

        foreach ($this->leaves() as $path => $leaf) {
            $counts[$path] = \count($generator->names($path, $leaf, $target));
        }

        $this->assertSame([$target], array_values(array_unique($counts)));
    }

    public function testGeneratedNamesNeverCollideWithHandWrittenOnes(): void
    {
        foreach ($this->leaves() as $path => $leaf) {
            $generated = [];

            foreach ($leaf['series'] ?? [] as $series) {
                foreach ($leaf['models'] ?? [] as $model) {
                    $generated[] = $series . ' ' . $model;
                }
            }

            $this->assertSame($generated, array_unique($generated), $path . ' generates the same name twice');
        }
    }

    public function testEveryLeafShipsProductsAndAPriceRange(): void
    {
        foreach ($this->leaves() as $path => $leaf) {
            $this->assertNotEmpty($leaf['products'] ?? [], $path);
            $this->assertCount(2, $leaf['price'] ?? [], $path);
            $this->assertLessThan($leaf['price'][1], $leaf['price'][0], $path);
        }
    }

    public function testEveryReferencedImageFileExists(): void
    {
        $root = \dirname(__DIR__, 3) . '/src/Resources/demo-media';

        foreach (DemoBlueprint::productImages() as $image) {
            $this->assertFileExists($root . '/' . $image);
        }

        foreach (DemoBlueprint::salesChannels() as $channel) {
            foreach ($channel['tree'] as $node) {
                if (isset($node['image'])) {
                    $this->assertFileExists($root . '/' . $node['image']);
                }
            }
        }
    }

    public function testSalesChannelsAreDistinctInNameRootAndDomain(): void
    {
        $channels = DemoBlueprint::salesChannels();

        $this->assertGreaterThan(1, \count($channels), 'Multi-channel support needs more than one channel.');

        foreach (['name', 'rootCategory', 'domain'] as $field) {
            $values = array_column($channels, $field);
            $this->assertSame($values, array_unique($values), $field . ' must be unique per channel');
        }
    }

    public function testPropertyGroupOptionsAreUnique(): void
    {
        foreach (DemoBlueprint::propertyGroups() as $key => $group) {
            $this->assertSame($group['options'], array_unique($group['options']), $key);
        }
    }

    /**
     * @return array<string, array<string, mixed>> path => leaf node
     */
    private function leaves(): array
    {
        $leaves = [];

        foreach (DemoBlueprint::salesChannels() as $channelKey => $channel) {
            $this->collect($channel['tree'], $channelKey, $leaves);
        }

        $this->assertNotEmpty($leaves);

        return $leaves;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param array<string, array<string, mixed>> $leaves
     */
    private function collect(array $nodes, string $path, array &$leaves): void
    {
        foreach ($nodes as $node) {
            $nodePath = $path . '/' . $node['name'];

            if (!empty($node['children'])) {
                $this->collect($node['children'], $nodePath, $leaves);

                continue;
            }

            $leaves[$nodePath] = $node;
        }
    }
}
