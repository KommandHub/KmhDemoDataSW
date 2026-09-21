<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Service;

use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Mints the stable identifiers the whole generator is built on.
 *
 * Every entity this plugin may create gets a UUID derived from a namespace, an
 * entity type and a hierarchical path — never from a bare name. The original
 * demo-data plugin hashed category names alone, which meant "Footwear" under one
 * sales channel and "Footwear" under another resolved to the same row: the
 * second channel silently stole the first channel's category. Including the path
 * removes that whole class of collision.
 *
 * The namespace carries a version. Bumping it deliberately orphans the previous
 * generation of demo data instead of rewriting it in place — useful when the
 * blueprint changes shape, and the reason it is a constant rather than a config
 * value somebody could flip by accident.
 */
final class DemoIdGenerator
{
    public const NAMESPACE = 'kommandhub-demo-data:v1';

    /**
     * @param string ...$path hierarchical key parts, most significant first
     */
    public function id(string $type, string ...$path): string
    {
        return Uuid::fromStringToHex($this->key($type, ...$path));
    }

    /**
     * The human-readable key behind an id. Stored on entities we create as a
     * custom field so the data can be traced back to its blueprint node without
     * reversing a hash.
     */
    public function key(string $type, string ...$path): string
    {
        return self::NAMESPACE . ':' . $type . ':' . implode('/', $path);
    }
}
