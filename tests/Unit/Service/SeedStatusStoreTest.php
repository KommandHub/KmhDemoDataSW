<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Service;

use Kommandhub\DemoData\Service\SeedReport;
use Kommandhub\DemoData\Service\SeedStatusStore;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SeedStatusStoreTest extends TestCase
{
    private SystemConfigService $config;
    private SeedStatusStore $store;

    protected function setUp(): void
    {
        $stored = [];

        $this->config = $this->createMock(SystemConfigService::class);
        $this->config->method('set')->willReturnCallback(static function (string $key, $value) use (&$stored): void {
            $stored[$key] = $value;
        });
        // A regular closure, not an arrow function: arrow functions capture by
        // value, so this would keep reading the empty array it was built with.
        $this->config->method('get')->willReturnCallback(static function (string $key) use (&$stored) {
            return $stored[$key] ?? null;
        });

        $this->store = new SeedStatusStore($this->config);
    }

    public function testAnUntouchedInstallationIsIdle(): void
    {
        $this->assertSame(['state' => SeedStatusStore::STATE_IDLE], $this->store->read());
        $this->assertFalse($this->store->isRunning());
    }

    /**
     * Queued counts as running: the window between dispatching and the handler
     * picking the job up is exactly when a second click would do damage.
     */
    public function testQueuedCountsAsRunning(): void
    {
        $this->store->markQueued();

        $this->assertTrue($this->store->isRunning());
        $this->assertSame(SeedStatusStore::STATE_RUNNING, $this->store->read()['state']);
    }

    public function testFinishingRecordsTheReportAndClearsRunning(): void
    {
        $report = new SeedReport();
        $report->created('product', 3);
        $report->enriched('category');
        $report->note('Run theme:change afterwards.');

        $this->store->markQueued();
        $this->store->markRunning();
        $this->store->markFinished($report);

        $status = $this->store->read();

        $this->assertFalse($this->store->isRunning());
        $this->assertSame(SeedStatusStore::STATE_FINISHED, $status['state']);
        $this->assertSame(3, $status['created']);
        $this->assertSame(1, $status['enriched']);
        $this->assertSame(['Run theme:change afterwards.'], $status['notes']);
        $this->assertSame(['product', '3', '0', '0', '0', '0'], $status['rows'][0]);
        $this->assertNotEmpty($status['headers']);
    }

    public function testFailingRecordsWhyAndStopsLookingLikeItIsStillGoing(): void
    {
        $this->store->markQueued();
        $this->store->markFailed('No tax rate configured.');

        $status = $this->store->read();

        $this->assertSame(SeedStatusStore::STATE_FAILED, $status['state']);
        $this->assertSame('No tax rate configured.', $status['message']);
        $this->assertFalse($this->store->isRunning());
    }
}
