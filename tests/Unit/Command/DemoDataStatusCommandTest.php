<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Command;

use Kommandhub\DemoData\Command\DemoDataStatusCommand;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\EntityResolver;
use Kommandhub\DemoData\Service\ResolvedEntity;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Console\Tester\CommandTester;

class DemoDataStatusCommandTest extends TestCase
{
    public function testExecutePrintsPresentAndMissingChannels(): void
    {
        $salesChannelRepository = $this->createMock(EntityRepository::class);
        $categoryRepository = $this->createMock(EntityRepository::class);
        $productRepository = $this->createMock(EntityRepository::class);
        $resolver = $this->createMock(EntityResolver::class);
        $ids = new DemoIdGenerator();

        $resolver->method('ids')->willReturn($ids);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setUniqueIdentifier($ids->id('sales-channel', 'flagship'));
        $salesChannel->setName('Demo Storefront');
        $salesChannel->setNavigationCategoryId($ids->id('category', 'root'));

        $resolver->method('resolve')->willReturnCallback(function ($repo, $context, $id) use ($ids, $salesChannel) {
            if ($id === $ids->id('sales-channel', 'flagship')) {
                return ResolvedEntity::owned($ids->id('sales-channel', 'flagship'), $salesChannel);
            }

            return ResolvedEntity::missing($id);
        });

        $category = new CategoryEntity();
        $category->setUniqueIdentifier($ids->id('category', 'root'));
        $category->setName('Demo Root');
        $categorySearchResult = $this->createMock(EntitySearchResult::class);
        $categorySearchResult->method('getEntities')->willReturn(new CategoryCollection([$category]));
        $categoryRepository->method('search')->willReturn($categorySearchResult);

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('getTotal')->willReturn(5);
        $categoryRepository->method('searchIds')->willReturn($idSearchResult);
        $productRepository->method('searchIds')->willReturn($idSearchResult);

        $command = new DemoDataStatusCommand(
            $salesChannelRepository,
            $categoryRepository,
            $productRepository,
            $resolver
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();

        $this->assertStringContainsString('Kommandhub demo data status', $output);
        $this->assertStringContainsString('flagship', $output);
        $this->assertStringContainsString('present', $output);
        $this->assertStringContainsString('Demo Root', $output);
        $this->assertStringContainsString('Run kmh:demo-data:seed to create what is missing.', $output);
    }

    public function testExecuteReportsNoneWhenAChannelHasNoRootCategory(): void
    {
        $salesChannelRepository = $this->createMock(EntityRepository::class);
        $categoryRepository = $this->createMock(EntityRepository::class);
        $productRepository = $this->createMock(EntityRepository::class);
        $resolver = $this->createMock(EntityResolver::class);
        $ids = new DemoIdGenerator();

        $resolver->method('ids')->willReturn($ids);
        $resolver->method('resolve')->willReturnCallback(function ($repo, $context, $id) use ($ids) {
            if ($id === $ids->id('sales-channel', 'flagship')) {
                return ResolvedEntity::owned($id, null);
            }

            return ResolvedEntity::missing($id);
        });

        $zero = $this->createMock(IdSearchResult::class);
        $zero->method('getTotal')->willReturn(0);
        $productRepository->method('searchIds')->willReturn($zero);

        $command = new DemoDataStatusCommand(
            $salesChannelRepository,
            $categoryRepository,
            $productRepository,
            $resolver
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('none', $output);
        $this->assertStringContainsString('0', $output);
    }
}
