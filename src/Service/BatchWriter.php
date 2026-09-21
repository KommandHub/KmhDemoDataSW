<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * Writes a large payload to the DAL in bounded chunks.
 *
 * Seeding two thousand products, eleven thousand reviews and six hundred orders
 * means handing the writer more rows than it is comfortable to send in one
 * statement. Chunking is the platform's own answer to that, and it necessarily
 * means calling the repository inside a loop.
 *
 * That is why this class exists rather than the loop being copied into six
 * seeders. Shopware's `noEntityRepositoryInLoop` rule exists to catch N:1
 * queries — a repository call that runs once per *entity*. A chunked write runs
 * once per *batch* and is the fix for that problem, not an instance of it. The
 * rule cannot tell the two apart, so it is suppressed here, once, against this
 * file alone (see phpstan.dist.neon) instead of being waved through wherever a
 * seeder happens to write.
 *
 * Nothing else belongs in here. If a call is per-entity, it is the N:1 the rule
 * is warning about and it wants batching, not a home in this class.
 */
class BatchWriter
{
    /**
     * Rows per statement. Large enough that the round trips do not dominate,
     * small enough that one order — with its line items, delivery, transaction
     * and two addresses — does not make the statement unwieldy.
     */
    public const DEFAULT_SIZE = 40;

    /**
     * @template TCollection of EntityCollection
     *
     * @param EntityRepository<TCollection> $repository
     * @param array<int, array<string, mixed>> $payloads
     */
    public function create(EntityRepository $repository, array $payloads, Context $context, int $size = self::DEFAULT_SIZE): void
    {
        foreach (array_chunk($payloads, max(1, $size)) as $batch) {
            $repository->create($batch, $context);
        }
    }

    /**
     * @template TCollection of EntityCollection
     *
     * @param EntityRepository<TCollection> $repository
     * @param array<int, array<string, mixed>> $payloads
     */
    public function update(EntityRepository $repository, array $payloads, Context $context, int $size = self::DEFAULT_SIZE): void
    {
        foreach (array_chunk($payloads, max(1, $size)) as $batch) {
            $repository->update($batch, $context);
        }
    }

    /**
     * @template TCollection of EntityCollection
     *
     * @param EntityRepository<TCollection> $repository
     * @param array<int, array<string, mixed>> $payloads
     */
    public function upsert(EntityRepository $repository, array $payloads, Context $context, int $size = self::DEFAULT_SIZE): void
    {
        foreach (array_chunk($payloads, max(1, $size)) as $batch) {
            $repository->upsert($batch, $context);
        }
    }
}
