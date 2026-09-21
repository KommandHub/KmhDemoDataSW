<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\MessageQueue;

use Kommandhub\DemoData\MessageQueue\GenerateDemoDataHandler;
use Kommandhub\DemoData\MessageQueue\GenerateDemoDataMessage;
use Kommandhub\DemoData\Service\DemoDataSeeder;
use Kommandhub\DemoData\Service\SeedReport;
use Kommandhub\DemoData\Service\SeedStatusStore;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GenerateDemoDataHandlerTest extends TestCase
{
    public function testItPassesTheMessageOptionsStraightToTheSeeder(): void
    {
        $seeder = $this->createMock(DemoDataSeeder::class);
        $status = $this->createMock(SeedStatusStore::class);

        $status->expects($this->once())->method('markRunning');
        $status->expects($this->once())->method('markFinished');
        $status->expects($this->never())->method('markFailed');

        $seeder->expects($this->once())
            ->method('seed')
            ->with($this->anything(), ['trade'], false, 7, false)
            ->willReturn(new SeedReport());

        $handler = new GenerateDemoDataHandler($seeder, $status, $this->createMock(LoggerInterface::class));
        $handler(new GenerateDemoDataMessage(['trade'], 7, false, false));
    }

    /**
     * A failed demo seed is worth reporting to whoever asked for it, not worth
     * being retried forever by the queue.
     */
    public function testAFailureIsRecordedRatherThanThrown(): void
    {
        $seeder = $this->createMock(DemoDataSeeder::class);
        $seeder->method('seed')->willThrowException(new \RuntimeException('no tax rate'));

        $status = $this->createMock(SeedStatusStore::class);
        $status->expects($this->once())->method('markFailed')->with('no tax rate');
        $status->expects($this->never())->method('markFinished');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $handler = new GenerateDemoDataHandler($seeder, $status, $logger);
        $handler(new GenerateDemoDataMessage());
    }
}
