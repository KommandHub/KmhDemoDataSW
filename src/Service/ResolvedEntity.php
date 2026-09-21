<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Service;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;

/**
 * The outcome of looking for one blueprint node in the installation.
 */
final class ResolvedEntity
{
    private function __construct(
        public readonly string $id,
        public readonly bool $exists,
        public readonly bool $adopted,
        public readonly ?Entity $entity
    ) {
    }

    /**
     * Nothing matched; `$id` is the deterministic id this plugin will create.
     */
    public static function missing(string $id): self
    {
        return new self($id, false, false, null);
    }

    /**
     * Found under this plugin's own deterministic id.
     */
    public static function owned(string $id, ?Entity $entity): self
    {
        return new self($id, true, false, $entity);
    }

    /**
     * Found by natural key under an id this plugin did not mint. The existing
     * row wins: we write to *its* id, never create a second one beside it.
     */
    public static function adopted(string $id, ?Entity $entity): self
    {
        return new self($id, true, true, $entity);
    }

    /**
     * Current value of a field on the matched entity, or null when nothing
     * matched or the field is unset.
     */
    public function current(string $field): mixed
    {
        if ($this->entity === null) {
            return null;
        }

        try {
            return $this->entity->get($field);
        } catch (\Throwable) {
            return null;
        }
    }

    public function isEmpty(string $field): bool
    {
        $value = $this->current($field);

        return $value === null || $value === '' || $value === [];
    }
}
