<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Command;

use Kommandhub\DemoData\Command\SeedDemoDataCommand;
use Kommandhub\DemoData\Exception\DemoDataException;
use Kommandhub\DemoData\Service\DemoDataSeeder;
use Kommandhub\DemoData\Service\SeedReport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SeedDemoDataCommandTest extends TestCase
{
    private DemoDataSeeder $seeder;
    private SeedDemoDataCommand $command;

    protected function setUp(): void
    {
        $this->seeder = $this->createMock(DemoDataSeeder::class);
        $this->command = new SeedDemoDataCommand($this->seeder);
    }

    public function testExecuteSuccessful(): void
    {
        $report = new SeedReport();
        $report->created('Product', 10);
        $report->enriched('Category', 5);
        $report->note('Test Note');

        $this->seeder->expects($this->once())
            ->method('seed')
            ->willReturn($report);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();

        $this->assertStringContainsString('Kommandhub demo data', $output);
        $this->assertStringContainsString('Product', $output);
        $this->assertStringContainsString('Worth knowing', $output);
        $this->assertStringContainsString('Test Note', $output);
        $this->assertStringContainsString('Demo data up to date: 10 entities created, 5 existing entities completed.', $output);
    }

    public function testExecuteNothingToDo(): void
    {
        $report = new SeedReport();

        $this->seeder->expects($this->once())
            ->method('seed')
            ->willReturn($report);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();

        $this->assertStringContainsString('Nothing to do — the demo catalogue is already complete.', $output);
    }

    public function testExecuteWithInvalidChannel(): void
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['--channel' => ['non-existent']]);

        $this->assertEquals(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString('Unknown channel key(s): non-existent', $tester->getDisplay());
    }

    public function testExecuteWithInvalidPerCategory(): void
    {
        $tester = new CommandTester($this->command);

        $tester->execute(['--per-category' => '0']);
        $this->assertEquals(Command::INVALID, $tester->getStatusCode());

        $tester->execute(['--per-category' => 'abc']);
        $this->assertEquals(Command::INVALID, $tester->getStatusCode());
    }

    public function testExecuteException(): void
    {
        $this->seeder->method('seed')->willThrowException(new DemoDataException('Seed failed'));

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertEquals(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Seed failed', $tester->getDisplay());
    }
}
