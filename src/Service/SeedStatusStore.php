<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Remembers what the last generation run did, so the admin page can show it.
 *
 * Kept in the system config rather than a table of its own: one row of status
 * does not justify an entity, a definition and a migration, and a migration is
 * a thing installations carry forever.
 *
 * The key deliberately sits outside the `config.` prefix that backs the plugin's
 * settings card, so a merchant editing settings never sees run state and
 * clearing run state never disturbs settings.
 */
class SeedStatusStore
{
    public const STATE_IDLE = 'idle';
    public const STATE_RUNNING = 'running';
    public const STATE_FINISHED = 'finished';
    public const STATE_FAILED = 'failed';

    private const KEY = 'KmhDemoDataSW.runtime.lastRun';

    public function __construct(private readonly SystemConfigService $systemConfig)
    {
    }

    public function markQueued(): void
    {
        $this->write(['state' => self::STATE_RUNNING, 'queuedAt' => $this->now(), 'message' => null]);
    }

    public function markRunning(): void
    {
        $current = $this->read();
        $current['state'] = self::STATE_RUNNING;
        $current['startedAt'] = $this->now();

        $this->write($current);
    }

    public function markFinished(SeedReport $report): void
    {
        $this->write([
            'state' => self::STATE_FINISHED,
            'finishedAt' => $this->now(),
            'created' => $report->totalCreated(),
            'enriched' => $report->totalEnriched(),
            'headers' => $report->headers(),
            'rows' => $report->toTable(),
            'notes' => $report->notes(),
            'message' => null,
        ]);
    }

    public function markFailed(string $message): void
    {
        $this->write([
            'state' => self::STATE_FAILED,
            'finishedAt' => $this->now(),
            'message' => $message,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        $stored = $this->systemConfig->get(self::KEY);

        if (!\is_array($stored) || $stored === []) {
            return ['state' => self::STATE_IDLE];
        }

        /** @var array<string, mixed> $stored */
        return $stored;
    }

    public function isRunning(): bool
    {
        return ($this->read()['state'] ?? self::STATE_IDLE) === self::STATE_RUNNING;
    }

    /**
     * @param array<string, mixed> $status
     */
    private function write(array $status): void
    {
        $this->systemConfig->set(self::KEY, $status);
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format(\DATE_ATOM);
    }
}
