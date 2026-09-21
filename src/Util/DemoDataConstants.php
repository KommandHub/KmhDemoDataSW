<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Util;

/**
 * Canonical names for everything this plugin writes into shared Shopware
 * storage.
 *
 * Custom-field keys are global across the installation and are the lookup key
 * for stored data. Defining them here — and only here — means a rename is a
 * one-line change that the type system can follow, instead of a string hunt
 * that silently orphans existing rows.
 *
 * These fields are also the audit trail: every row this plugin creates carries
 * the blueprint key it came from, so "did we generate this, or did a merchant?"
 * is answerable without reversing a UUID hash.
 */
final class DemoDataConstants
{
    public const CUSTOM_FIELD_SET = 'kmh_demo_data';

    /**
     * The blueprint key the entity was generated from.
     */
    public const FIELD_SOURCE_KEY = 'kmh_demo_data_source_key';

    /**
     * Marks an entity as generated demo data.
     */
    public const FIELD_GENERATED = 'kmh_demo_data_generated';

    /**
     * Blueprint sales-channel key the entity belongs to.
     */
    public const FIELD_CHANNEL_KEY = 'kmh_demo_data_channel_key';
}
