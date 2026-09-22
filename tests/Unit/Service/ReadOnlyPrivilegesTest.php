<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Service;

use Kommandhub\DemoData\Service\ReadOnlyPrivileges;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class ReadOnlyPrivilegesTest extends TestCase
{
    public function testGrantsReadOnEveryEntityAndNothingElse(): void
    {
        $privileges = $this->privileges(['product', 'order', 'some_plugin_entity'])->all();

        $this->assertContains('product:read', $privileges);
        $this->assertContains('order:read', $privileges);
        // Entities a plugin adds are covered without anyone listing them.
        $this->assertContains('some_plugin_entity:read', $privileges);

        foreach ($privileges as $privilege) {
            $this->assertDoesNotMatchRegularExpression(
                '/:(create|update|delete)$/',
                $privilege,
                'a read-only role must not carry a write privilege'
            );
        }
    }

    /**
     * The API half and the administration half are different strings and the
     * role needs both: entity privileges decide what answers, `<module>.viewer`
     * decides what is drawn.
     */
    public function testGrantsTheAdministrationItsOwnViewerKeys(): void
    {
        $privileges = $this->privileges(['product'])->all();

        $this->assertContains('product.viewer', $privileges);
        $this->assertContains('order.viewer', $privileges);
        $this->assertContains('users_and_permissions.viewer', $privileges);

        foreach ($privileges as $privilege) {
            $this->assertDoesNotMatchRegularExpression(
                '/\.(creator|editor|deleter)$/',
                $privilege,
                'a read-only role must not carry an editing role key'
            );
        }
    }

    /**
     * Read access to these is a copy of the shop's credentials, not a tour of
     * the shop: system_config holds plugin secrets in plain text.
     */
    public function testWithholdsTheEntitiesThatHoldCredentials(): void
    {
        $privileges = $this->privileges(['product', 'system_config', 'integration', 'user_access_key', 'user_recovery'])->all();

        $this->assertNotContains('system_config:read', $privileges);
        $this->assertNotContains('integration:read', $privileges);
        $this->assertNotContains('user_access_key:read', $privileges);
        $this->assertNotContains('user_recovery:read', $privileges);
        $this->assertContains('product:read', $privileges);
    }

    public function testIsStableAndFreeOfDuplicates(): void
    {
        $privileges = $this->privileges(['product', 'product'])->all();

        $this->assertSame(array_values(array_unique($privileges)), $privileges);
        $sorted = $privileges;
        sort($sorted);
        $this->assertSame($sorted, $privileges, 'a stable order keeps a re-run from rewriting the role');
    }

    /**
     * @param array<int, string> $entities
     */
    private function privileges(array $entities): ReadOnlyPrivileges
    {
        $definitions = [];

        foreach ($entities as $entity) {
            $definition = $this->createMock(EntityDefinition::class);
            $definition->method('getEntityName')->willReturn($entity);
            $definitions[$entity] = $definition;
        }

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn($definitions);

        return new ReadOnlyPrivileges($registry);
    }
}
