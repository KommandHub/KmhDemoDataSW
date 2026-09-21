<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Service;

/**
 * Tallies what each run actually did.
 *
 * The counters are the readable proof of idempotency: a second run against an
 * unchanged installation must report zero created and zero enriched. Without
 * that, "it is idempotent" is a claim nobody can check without a database
 * client.
 */
final class SeedReport
{
    /**
     * @var array<string, array{created: int, reused: int, adopted: int, enriched: int, skipped: int}>
     */
    private array $rows = [];

    /**
     * @var array<int, string>
     */
    private array $notes = [];

    public function created(string $entity, int $count = 1): void
    {
        $this->add($entity, 'created', $count);
    }

    public function reused(string $entity, int $count = 1): void
    {
        $this->add($entity, 'reused', $count);
    }

    /**
     * Reused, but under an id this plugin did not mint — a pre-existing row
     * matched by natural key and taken over instead of duplicated.
     */
    public function adopted(string $entity, int $count = 1): void
    {
        $this->add($entity, 'adopted', $count);
        $this->add($entity, 'reused', $count);
    }

    public function enriched(string $entity, int $count = 1): void
    {
        $this->add($entity, 'enriched', $count);
    }

    public function skipped(string $entity, int $count = 1): void
    {
        $this->add($entity, 'skipped', $count);
    }

    public function note(string $message): void
    {
        $this->notes[] = $message;
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function toTable(): array
    {
        $rows = [];

        foreach ($this->rows as $entity => $counts) {
            $rows[] = [
                $entity,
                (string)$counts['created'],
                (string)$counts['reused'],
                (string)$counts['adopted'],
                (string)$counts['enriched'],
                (string)$counts['skipped'],
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    public function headers(): array
    {
        return ['Entity', 'Created', 'Reused', 'Adopted', 'Enriched', 'Skipped'];
    }

    /**
     * @return array<int, string>
     */
    public function notes(): array
    {
        return $this->notes;
    }

    public function totalCreated(): int
    {
        return array_sum(array_column($this->rows, 'created'));
    }

    public function totalEnriched(): int
    {
        return array_sum(array_column($this->rows, 'enriched'));
    }

    /**
     * @param 'adopted'|'created'|'enriched'|'reused'|'skipped' $bucket
     */
    private function add(string $entity, string $bucket, int $count): void
    {
        $row = $this->rows[$entity] ?? ['created' => 0, 'reused' => 0, 'adopted' => 0, 'enriched' => 0, 'skipped' => 0];
        $row[$bucket] += $count;

        $this->rows[$entity] = $row;
    }
}
