<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Service;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;

/**
 * Every privilege a look-but-don't-touch administrator needs, and nothing else.
 *
 * An ACL role in Shopware stores two different kinds of string and needs both:
 *
 *  - `<entity>:read` decides what the API will answer. The DAL registry knows
 *    every entity in the installation, including the ones plugins add, so that
 *    half is computed rather than listed.
 *  - `<module>.viewer` decides what the administration draws. Those keys exist
 *    only in the admin's JavaScript (`addPrivilegeMappingEntry`), are never
 *    expanded on the server, and are compared verbatim against the flat list of
 *    privileges on the logged-in user's roles. Without them the API answers
 *    everything and the administration shows an empty menu.
 *
 * The module list is therefore hard-coded, and the list is Shopware's own as of
 * 6.7. Regenerate it after a major update with:
 *
 *   grep -rho "key: '[a-z_]*'" vendor/shopware/administration/Resources/app/administration/src \
 *     --include='*.js' --include='*.ts' | sort -u
 *
 * A key that has disappeared costs nothing — it is a string nobody compares
 * against any more. A key that is missing hides that module from the menu.
 */
final class ReadOnlyPrivileges
{
    /**
     * Administration modules that define a `viewer` role, in Shopware 6.7.
     *
     * @var array<int, string>
     */
    public const ADMIN_MODULES = [
        'category',
        'cms',
        'country',
        'currencies',
        'custom_field',
        'customer',
        'customer_groups',
        'delivery_times',
        'document',
        'flow',
        'integration',
        'landing_page',
        'language',
        'mail_templates',
        'measurement',
        'media',
        'newsletter_recipient',
        'number_ranges',
        'order',
        'order_refund',
        'payment',
        'product',
        'product_feature_sets',
        'product_manufacturer',
        'product_search_config',
        'product_stream',
        'promotion',
        'property',
        'review',
        'rule',
        'sales_channel',
        'salutation',
        'scale_unit',
        'shipping',
        'snippet',
        'state_machine',
        'tag',
        'tax',
        'users_and_permissions',
    ];

    /**
     * Entities whose rows are credentials, withheld however broad the role is.
     *
     * Read access to these is not a tour of the shop, it is a copy of its keys:
     * `system_config` holds every plugin's secret in plain text — a live
     * payment key among them — and the other three hold access keys and
     * password-recovery hashes. The account this role is written for goes to
     * people outside the business, so they stay out.
     *
     * The administration never lists them without the additional privileges
     * (`system.system_config` and friends) that this role also withholds, so
     * nothing in the interface breaks by their absence.
     *
     * @var array<int, string>
     */
    public const WITHHELD_ENTITIES = [
        'integration',
        'system_config',
        'user_access_key',
        'user_recovery',
    ];

    public function __construct(private readonly DefinitionInstanceRegistry $definitionRegistry)
    {
    }

    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        $privileges = [];

        foreach (self::ADMIN_MODULES as $module) {
            $privileges[] = $module . '.viewer';
        }

        foreach ($this->definitionRegistry->getDefinitions() as $definition) {
            $entity = $definition->getEntityName();

            if (\in_array($entity, self::WITHHELD_ENTITIES, true)) {
                continue;
            }

            $privileges[] = $entity . ':' . AclRoleDefinition::PRIVILEGE_READ;
        }

        $privileges = array_values(array_unique($privileges));
        sort($privileges);

        return $privileges;
    }
}
