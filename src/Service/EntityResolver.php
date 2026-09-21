<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;

/**
 * Inspect-before-write.
 *
 * Every seeder asks this class the same question first: does this thing already
 * exist, and under which id? Two lookups, in order of confidence:
 *
 *  1. this plugin's deterministic id — the row we created on an earlier run;
 *  2. a natural key (name, product number, tax rate, …) — a row somebody else
 *     created that means the same thing.
 *
 * The second lookup is what stops the generator from bolting a parallel
 * "Colour" property group onto a shop that already has one. It is also why
 * writes are split into create / enrich: an adopted row belongs to the merchant,
 * so we fill its gaps and otherwise leave it alone.
 */
class EntityResolver
{
    public function __construct(private readonly DemoIdGenerator $ids)
    {
    }

    /**
     * @template TCollection of EntityCollection
     *
     * @param EntityRepository<TCollection> $repository
     * @param array<int, Filter> $naturalKey filters that identify the same thing under a foreign id
     * @param array<int, string> $associations associations to load, so callers can inspect current values
     */
    public function resolve(
        EntityRepository $repository,
        Context $context,
        string $deterministicId,
        array $naturalKey = [],
        array $associations = []
    ): ResolvedEntity {
        $owned = $this->fetch($repository, $context, [new EqualsFilter('id', $deterministicId)], $associations);

        if ($owned !== null) {
            return ResolvedEntity::owned($deterministicId, $owned);
        }

        if ($naturalKey === []) {
            return ResolvedEntity::missing($deterministicId);
        }

        $foreign = $this->fetch($repository, $context, $naturalKey, $associations);

        if ($foreign !== null) {
            return ResolvedEntity::adopted($foreign->getUniqueIdentifier(), $foreign);
        }

        return ResolvedEntity::missing($deterministicId);
    }

    /**
     * Shorthand for a resolve keyed on a single equals-match field.
     *
     * @template TCollection of EntityCollection
     *
     * @param EntityRepository<TCollection> $repository
     * @param array<int, string> $associations
     */
    public function resolveByField(
        EntityRepository $repository,
        Context $context,
        string $deterministicId,
        string $field,
        bool|float|int|string|null $value,
        array $associations = []
    ): ResolvedEntity {
        return $this->resolve(
            $repository,
            $context,
            $deterministicId,
            [new EqualsFilter($field, $value)],
            $associations
        );
    }

    /**
     * Narrows a full payload down to what may safely be written to an entity
     * that already exists.
     *
     * Scalars are only filled in where the stored value is empty — a merchant
     * who renamed a demo product keeps that name. Associations are diffed
     * against what is already linked and only the missing links are written, so
     * a run that changes nothing produces no update at all.
     *
     * A third category, `$overwrite`, is for fields this plugin owns outright
     * rather than merely fills — a generated cover image, say. They are written
     * whenever the stored value differs, and left alone when it already matches,
     * so they still settle to a no-op on a second run.
     *
     * @param array<string, mixed> $payload
     * @param array<int, string> $fillIfEmpty scalar fields to top up when blank
     * @param array<int, string> $additive association keys to top up (must be loaded on the resolved entity)
     * @param array<int, string> $overwrite scalar fields to correct when they differ
     *
     * @return array<string, mixed>|null null when there is nothing worth writing
     */
    public function enrichmentPayload(
        ResolvedEntity $resolved,
        array $payload,
        array $fillIfEmpty,
        array $additive = [],
        array $overwrite = []
    ): ?array {
        $update = [];

        foreach ($fillIfEmpty as $field) {
            if (\array_key_exists($field, $payload) && $resolved->isEmpty($field)) {
                $update[$field] = $payload[$field];
            }
        }

        foreach ($overwrite as $field) {
            if (\array_key_exists($field, $payload) && $resolved->current($field) !== $payload[$field]) {
                $update[$field] = $payload[$field];
            }
        }

        foreach ($additive as $field) {
            $missing = $this->missingLinks($resolved, $field, $payload);

            if ($missing !== []) {
                $update[$field] = $missing;
            }
        }

        if ($update === []) {
            return null;
        }

        return ['id' => $resolved->id] + $update;
    }

    /**
     * The entries of an association payload that are not linked yet.
     *
     * Re-stating a complete association list is harmless but not free: it is a
     * write, and it makes every run report work it did not really do. Diffing
     * first is what lets a second run honestly say "nothing changed".
     *
     * A collection that was not loaded cannot be diffed, so the whole list is
     * returned — correct, just not minimal.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<int, array<string, mixed>>
     */
    private function missingLinks(ResolvedEntity $resolved, string $field, array $payload): array
    {
        if (!\array_key_exists($field, $payload) || !\is_array($payload[$field]) || $payload[$field] === []) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $entries */
        $entries = $payload[$field];
        $current = $resolved->current($field);

        if (!$current instanceof EntityCollection) {
            return $entries;
        }

        $linked = $current->getIds();

        return array_values(array_filter(
            $entries,
            static fn (array $entry): bool => !isset($entry['id']) || !\in_array($entry['id'], $linked, true)
        ));
    }

    public function ids(): DemoIdGenerator
    {
        return $this->ids;
    }

    /**
     * @template TCollection of EntityCollection
     *
     * @param EntityRepository<TCollection> $repository
     * @param array<int, Filter> $filters
     * @param array<int, string> $associations
     */
    private function fetch(
        EntityRepository $repository,
        Context $context,
        array $filters,
        array $associations
    ): ?Entity {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        foreach ($filters as $filter) {
            $criteria->addFilter($filter);
        }

        foreach ($associations as $association) {
            $criteria->addAssociation($association);
        }

        return $repository->search($criteria, $context)->getEntities()->first();
    }
}
