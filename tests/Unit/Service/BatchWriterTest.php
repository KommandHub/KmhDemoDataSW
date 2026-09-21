<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Service;

use Kommandhub\DemoData\Service\BatchWriter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;

class BatchWriterTest extends TestCase
{
    public function testCreateSplitsPayloadsAndClampsNonPositiveSize(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = Context::createDefaultContext();
        $batches = [];
        $repository->expects($this->exactly(2))->method('create')->willReturnCallback(
            function (array $batch, Context $context) use (&$batches): EntityWrittenContainerEvent {
                $batches[] = $batch;

                return $this->writtenEvent();
            }
        );

        (new BatchWriter())->create($repository, [['id' => 'a'], ['id' => 'b']], $context, 0);

        $this->assertSame([[['id' => 'a']], [['id' => 'b']]], $batches);
    }

    public function testUpdateSplitsPayloadsIntoConfiguredBatches(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = Context::createDefaultContext();
        $batches = [];
        $repository->expects($this->exactly(2))->method('update')->willReturnCallback(
            function (array $batch, Context $context) use (&$batches): EntityWrittenContainerEvent {
                $batches[] = $batch;

                return $this->writtenEvent();
            }
        );

        (new BatchWriter())->update(
            $repository,
            [['id' => 'a'], ['id' => 'b'], ['id' => 'c']],
            $context,
            2
        );

        $this->assertSame([[['id' => 'a'], ['id' => 'b']], [['id' => 'c']]], $batches);
    }

    public function testUpsertSplitsPayloadsIntoConfiguredBatches(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = Context::createDefaultContext();
        $batches = [];
        $repository->expects($this->exactly(2))->method('upsert')->willReturnCallback(
            function (array $batch, Context $context) use (&$batches): EntityWrittenContainerEvent {
                $batches[] = $batch;

                return $this->writtenEvent();
            }
        );

        (new BatchWriter())->upsert(
            $repository,
            [['id' => 'a'], ['id' => 'b'], ['id' => 'c']],
            $context,
            2
        );

        $this->assertSame([[['id' => 'a'], ['id' => 'b']], [['id' => 'c']]], $batches);
    }

    private function writtenEvent(): EntityWrittenContainerEvent
    {
        return EntityWrittenContainerEvent::createWithWrittenEvents([], Context::createDefaultContext(), []);
    }
}
