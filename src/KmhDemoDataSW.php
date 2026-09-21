<?php

declare(strict_types=1);

namespace Kommandhub\DemoData;

use Kommandhub\DemoData\Installer\CustomFieldsInstaller;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Main Shopware plugin class for Kommandhub Demo Data for Shopware 6.
 *
 * Responsibilities:
 * - Extends the DI container with this plugin's package configuration.
 * - Manages the install / update / activate / deactivate / uninstall lifecycle.
 *
 * Keep this class thin: it is lifecycle glue that can only run with a booted
 * kernel, so it is excluded from the no-kernel coverage gate (see
 * phpunit.dist.xml) and is covered by the @group kernel integration tests.
 */
class KmhDemoDataSW extends Plugin
{
    /**
     * Allow composer commands during plugin execution.
     */
    public function executeComposerCommands(): bool
    {
        return true;
    }

    /**
     * Load additional service configuration files.
     *
     * Shopware loads Resources/config/services.yml on its own; this adds the
     * Resources/config/packages/*.yaml bundle configuration (the monolog
     * channel) on top.
     *
     * @throws \Exception
     *
     * @codeCoverageIgnore
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $locator = new FileLocator('Resources/config');

        $resolver = new LoaderResolver([
            new YamlFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
        ]);

        $loader = new DelegatingLoader($resolver);

        $configPath = rtrim($this->getPath(), '/') . '/Resources/config';

        $loader->load($configPath . '/{packages}/*.yaml', 'glob');
    }

    /**
     * Plugin installation lifecycle hook.
     */
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $this->runInstallers($installContext->getContext());
    }

    /**
     * Plugin update lifecycle hook.
     *
     * Re-runs the installers so stored identifiers and custom fields are
     * migrated when classes move between versions. Without this, an update (as
     * opposed to a fresh install) can leave a dangling handler identifier.
     */
    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);

        $this->runInstallers($updateContext->getContext());
    }

    /**
     * Plugin activation lifecycle hook.
     */
    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);
    }

    /**
     * Plugin deactivation lifecycle hook.
     */
    public function deactivate(DeactivateContext $deactivateContext): void
    {
        parent::deactivate($deactivateContext);
    }

    /**
     * Plugin uninstall lifecycle hook.
     *
     * Payment methods are never deleted — that would break historical orders.
     * User data is only removed when the merchant did not ask to keep it.
     */
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->getCustomFieldsInstaller()->uninstall($uninstallContext->getContext());
    }

    /**
     * Runs every installer that must be idempotent across install and update.
     */
    private function runInstallers(\Shopware\Core\Framework\Context $context): void
    {
        $installer = $this->getCustomFieldsInstaller();
        $installer->install($context);
        $installer->addRelations($context);
    }

    /**
     * Returns the configured custom field installer.
     */
    private function getCustomFieldsInstaller(): CustomFieldsInstaller
    {
        $container = $this->requireContainer();

        $setRepo = $container->get('custom_field_set.repository');
        $relationRepo = $container->get('custom_field_set_relation.repository');

        if (!$setRepo instanceof EntityRepository || !$relationRepo instanceof EntityRepository) {
            throw new \RuntimeException('Invalid repository services.'); // @codeCoverageIgnore
        }

        return new CustomFieldsInstaller($setRepo, $relationRepo);
    }

    /**
     * The plugin container is null outside a booted kernel; every lifecycle
     * hook that reaches for a service must fail loudly rather than on a null
     * dereference three frames later.
     */
    private function requireContainer(): \Symfony\Component\DependencyInjection\ContainerInterface
    {
        if ($this->container === null) {
            throw new \RuntimeException('Container is not available.'); // @codeCoverageIgnore
        }

        return $this->container;
    }
}
