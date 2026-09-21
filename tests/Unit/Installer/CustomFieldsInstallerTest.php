<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Installer;

use Kommandhub\DemoData\Installer\CustomFieldsInstaller;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class CustomFieldsInstallerTest extends TestCase
{
    private EntityRepository $customFieldSetRepository;
    private EntityRepository $customFieldSetRelationRepository;
    private CustomFieldsInstaller $installer;
    private Context $context;

    protected function setUp(): void
    {
        $this->customFieldSetRepository = $this->createMock(EntityRepository::class);
        $this->customFieldSetRelationRepository = $this->createMock(EntityRepository::class);
        $this->installer = new CustomFieldsInstaller(
            $this->customFieldSetRepository,
            $this->customFieldSetRelationRepository
        );
        $this->context = Context::createDefaultContext();
    }

    public function testInstallWhenNotExists(): void
    {
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('getIds')->willReturn([]);
        $this->customFieldSetRepository->method('searchIds')->willReturn($idSearchResult);

        $this->customFieldSetRepository->expects($this->once())
            ->method('upsert');

        $this->installer->install($this->context);
    }

    public function testInstallWhenExists(): void
    {
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('getIds')->willReturn(['existing-id']);
        $this->customFieldSetRepository->method('searchIds')->willReturn($idSearchResult);

        $this->customFieldSetRepository->expects($this->never())
            ->method('upsert');

        $this->installer->install($this->context);
    }

    public function testAddRelations(): void
    {
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('getIds')->willReturn(['fieldset-id']);
        $this->customFieldSetRepository->method('searchIds')->willReturn($idSearchResult);

        $relationIdSearchResult = $this->createMock(IdSearchResult::class);
        $relationIdSearchResult->method('getTotal')->willReturn(0);
        $this->customFieldSetRelationRepository->method('searchIds')->willReturn($relationIdSearchResult);

        $this->customFieldSetRelationRepository->expects($this->once())
            ->method('upsert');

        $this->installer->addRelations($this->context);
    }

    public function testAddRelationsWhenAlreadyExist(): void
    {
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('getIds')->willReturn(['fieldset-id']);
        $this->customFieldSetRepository->method('searchIds')->willReturn($idSearchResult);

        $relationIdSearchResult = $this->createMock(IdSearchResult::class);
        $relationIdSearchResult->method('getTotal')->willReturn(1);
        $this->customFieldSetRelationRepository->method('searchIds')->willReturn($relationIdSearchResult);

        $this->customFieldSetRelationRepository->expects($this->never())
            ->method('upsert');

        $this->installer->addRelations($this->context);
    }

    public function testUninstall(): void
    {
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('getIds')->willReturn(['fieldset-id']);
        $this->customFieldSetRepository->method('searchIds')->willReturn($idSearchResult);

        $this->customFieldSetRepository->expects($this->once())
            ->method('delete')
            ->with([['id' => 'fieldset-id']], $this->context);

        $this->installer->uninstall($this->context);
    }

    public function testUninstallWhenNotExists(): void
    {
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('getIds')->willReturn([]);
        $this->customFieldSetRepository->method('searchIds')->willReturn($idSearchResult);

        $this->customFieldSetRepository->expects($this->never())
            ->method('delete');

        $this->installer->uninstall($this->context);
    }
}
