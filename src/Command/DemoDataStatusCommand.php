<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Command;

use Kommandhub\DemoData\Blueprint\DemoBlueprint;
use Kommandhub\DemoData\Service\EntityResolver;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Read-only view of what the seeder would find.
 *
 * Writes nothing. This is the command to run before and after a seed to see
 * whether anything actually changed, and the one to run when a catalogue looks
 * wrong to find out which sales channel owns which root category.
 */
#[AsCommand(
    name: 'kmh:demo-data:status',
    description: 'Reports which demo sales channels, categories and products already exist.',
)]
class DemoDataStatusCommand extends Command
{
    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param EntityRepository<CategoryCollection> $categoryRepository
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $productRepository,
        private readonly EntityResolver $resolver
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createCLIContext();

        $io->title('Kommandhub demo data status');

        $rows = [];

        foreach (DemoBlueprint::salesChannels() as $channelKey => $definition) {
            $resolved = $this->resolver->resolve(
                $this->salesChannelRepository,
                $context,
                $this->resolver->ids()->id('sales-channel', $channelKey),
                [new EqualsAnyFilter('name', array_merge([$definition['name']], $definition['adopt']))]
            );

            if (!$resolved->exists) {
                $rows[] = [$channelKey, 'missing', '—', '0', '0'];

                continue;
            }

            /** @var SalesChannelEntity|null $channel */
            $channel = $resolved->entity;
            $rootId = $channel?->getNavigationCategoryId();
            $state = $resolved->adopted ? 'adopted: ' . ($channel?->getName() ?? '?') : 'present';

            $rows[] = [
                $channelKey,
                $state,
                $rootId !== null ? $this->categoryName($context, $rootId) : 'none',
                (string)$this->countCategories($context, $rootId),
                (string)$this->countProducts($context, $resolved->id),
            ];
        }

        $io->table(['Channel', 'Sales channel', 'Root category', 'Categories below root', 'Visible products'], $rows);

        $missing = array_filter($rows, static fn (array $row): bool => $row[1] === 'missing');

        if ($missing !== []) {
            $io->note('Run kmh:demo-data:seed to create what is missing.');
        }

        return Command::SUCCESS;
    }

    private function categoryName(Context $context, string $categoryId): string
    {
        $category = $this->categoryRepository->search(new Criteria([$categoryId]), $context)->getEntities()->first();

        return $category?->getName() ?? 'unknown';
    }

    private function countCategories(Context $context, ?string $rootId): int
    {
        if ($rootId === null) {
            return 0;
        }

        $criteria = new Criteria();
        // `path` holds the full ancestor chain, so a single contains-match counts
        // the whole subtree without walking it level by level.
        $criteria->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter('path', $rootId));

        return $this->categoryRepository->searchIds($criteria, $context)->getTotal();
    }

    private function countProducts(Context $context, string $salesChannelId): int
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('visibilities.salesChannelId', $salesChannelId));
        $criteria->addFilter(new EqualsFilter('parentId', null));

        return $this->productRepository->searchIds($criteria, $context)->getTotal();
    }
}
