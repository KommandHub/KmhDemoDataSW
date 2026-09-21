<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Service;

use Kommandhub\DemoData\Service\SeedReport;
use PHPUnit\Framework\TestCase;

class SeedReportTest extends TestCase
{
    public function testAnEmptyRunReportsNothing(): void
    {
        $report = new SeedReport();

        $this->assertSame([], $report->toTable());
        $this->assertSame(0, $report->totalCreated());
        $this->assertSame(0, $report->totalEnriched());
    }

    public function testCountsAreTalliedPerEntity(): void
    {
        $report = new SeedReport();
        $report->created('product', 3);
        $report->reused('product');
        $report->enriched('product', 2);
        $report->created('category');

        $this->assertSame(4, $report->totalCreated());
        $this->assertSame(2, $report->totalEnriched());
        $this->assertSame(
            [
                ['product', '3', '1', '0', '2', '0'],
                ['category', '1', '0', '0', '0', '0'],
            ],
            $report->toTable()
        );
    }

    /**
     * An adopted row was reused, just not under our id — it has to count in both
     * columns or the reuse total under-reports.
     */
    public function testAdoptionCountsAsReuseToo(): void
    {
        $report = new SeedReport();
        $report->adopted('sales_channel');

        $this->assertSame([['sales_channel', '0', '1', '1', '0', '0']], $report->toTable());
    }

    public function testNotesAreCollected(): void
    {
        $report = new SeedReport();
        $report->note('Domain already in use.');

        $this->assertSame(['Domain already in use.'], $report->notes());
    }

    public function testHeadersMatchTheRowWidth(): void
    {
        $report = new SeedReport();
        $report->created('product');

        $this->assertCount(\count($report->headers()), $report->toTable()[0]);
    }
}
