<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\MessageQueue;

use Kommandhub\DemoData\Service\DemoDataSeeder;
use Kommandhub\DemoData\Service\SeedStatusStore;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs a queued generation and records how it went.
 *
 * The status is written before and after rather than only after, so an admin
 * watching the page can tell "still working" from "finished" — and so a run
 * that dies mid-way leaves a record saying it was running rather than looking
 * like it was never asked for.
 */
#[AsMessageHandler]
class GenerateDemoDataHandler
{
    public function __construct(
        private readonly DemoDataSeeder $seeder,
        private readonly SeedStatusStore $status,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(GenerateDemoDataMessage $message): void
    {
        $this->status->markRunning();

        try {
            // A queue worker has no request behind it, so this is the CLI case.
            $report = $this->seeder->seed(
                Context::createCLIContext(),
                $message->channels,
                $message->withMedia,
                $message->perCategory,
                $message->withOrders
            );

            $this->status->markFinished($report);
        } catch (\Throwable $exception) {
            // Swallowed on purpose: a failed demo seed is worth reporting to the
            // person who asked for it, not worth retrying forever in the queue.
            $this->status->markFailed($exception->getMessage());
            $this->logger->error('Demo data generation failed', ['error' => $exception->getMessage()]);
        }
    }
}
