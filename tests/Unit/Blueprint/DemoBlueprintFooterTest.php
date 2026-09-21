<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Blueprint;

use Kommandhub\DemoData\Blueprint\DemoBlueprint;
use PHPUnit\Framework\TestCase;

/**
 * The footer is shared across every sales channel, so a duplicate entry here is
 * a duplicate on every storefront at once.
 */
class DemoBlueprintFooterTest extends TestCase
{
    public function testFooterDeclaresBothRoots(): void
    {
        $footer = DemoBlueprint::footer();

        $this->assertNotSame('', $footer['navigationRoot']);
        $this->assertNotSame('', $footer['serviceRoot']);
        $this->assertNotSame($footer['navigationRoot'], $footer['serviceRoot']);
    }

    public function testFooterRootsDoNotCollideWithASalesChannelRoot(): void
    {
        $footer = DemoBlueprint::footer();
        $channelRoots = array_column(DemoBlueprint::salesChannels(), 'rootCategory');

        // All of these are root categories (parentId null), and the resolver
        // matches roots on name — a collision would hand a sales channel the
        // footer tree as its navigation.
        $this->assertNotContains($footer['navigationRoot'], $channelRoots);
        $this->assertNotContains($footer['serviceRoot'], $channelRoots);
    }

    public function testFooterColumnsAndEntriesAreUnique(): void
    {
        $footer = DemoBlueprint::footer();
        $columns = array_column($footer['navigation'], 'name');

        $this->assertSame($columns, array_unique($columns));

        $allEntries = [];

        foreach ($footer['navigation'] as $column) {
            $this->assertNotEmpty($column['children'], $column['name']);

            $names = array_column($column['children'], 'name');
            $this->assertSame($names, array_unique($names), $column['name']);

            $allEntries = [...$allEntries, ...$names];
        }

        $this->assertSame($allEntries, array_unique($allEntries), 'a footer entry appears in two columns');
    }

    /**
     * A footer entry without copy renders as a blank page, and it is linked
     * from the footer of every page in the shop.
     */
    public function testEveryFooterEntryHasSomethingToSay(): void
    {
        foreach ($this->entries() as $entry) {
            $this->assertArrayHasKey('body', $entry, $entry['name']);
            $this->assertStringStartsWith('<p>', $entry['body'], $entry['name']);
            $this->assertGreaterThan(200, \strlen($entry['body']), $entry['name'] . ' is barely a page');
        }
    }

    /**
     * Demo terms and privacy copy that reads as genuine is the one thing here
     * that could actually hurt somebody if the shop went live unedited.
     */
    public function testLegalPagesSayTheyAreNotRealLegalText(): void
    {
        $legal = ['Terms & Conditions', 'Privacy Policy', 'Cookie Policy', 'Right of Withdrawal', 'Imprint', 'Accessibility'];
        $checked = 0;

        foreach ($this->entries() as $entry) {
            if (!\in_array($entry['name'], $legal, true)) {
                continue;
            }

            ++$checked;
            $this->assertStringContainsString('demo content', $entry['body'], $entry['name']);
            $this->assertStringContainsString('not legal advice', $entry['body'], $entry['name']);
        }

        $this->assertCount($checked, $legal, 'a legal page went missing from the footer');
    }

    /**
     * @return array<int, array{name: string, body: string}>
     */
    private function entries(): array
    {
        $entries = [];

        foreach (DemoBlueprint::footer()['navigation'] as $column) {
            $entries = [...$entries, ...$column['children']];
        }

        $this->assertNotEmpty($entries);

        return $entries;
    }

    /**
     * The service menu is generated from the installation's sales channels, so
     * declaring entries here would mean the blueprint and the generator both
     * own its contents.
     */
    public function testServiceMenuHasNoDeclaredEntries(): void
    {
        $this->assertArrayNotHasKey('service', DemoBlueprint::footer());
    }
}
