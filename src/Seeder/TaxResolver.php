<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Seeder;

use Kommandhub\DemoData\Exception\DemoDataException;
use Kommandhub\DemoData\Service\SeedReport;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxEntity;

/**
 * Finds the tax rate a channel's products should carry.
 *
 * Tax is jurisdiction-specific and merchants configure it deliberately, so this
 * never creates a rate that is close to an existing one — it takes the exact
 * match if there is one, and otherwise falls back to the highest configured
 * rate rather than inventing tax policy for somebody's live shop.
 */
class TaxResolver
{
    /**
     * @param EntityRepository<TaxCollection> $taxRepository
     */
    public function __construct(private readonly EntityRepository $taxRepository)
    {
    }

    /**
     * @return array{id: string, rate: float}
     */
    public function resolve(Context $context, SeedReport $report, float $preferredRate): array
    {
        $exact = new Criteria();
        $exact->addFilter(new EqualsFilter('taxRate', $preferredRate));
        $exact->setLimit(1);

        /** @var TaxEntity|null $tax */
        $tax = $this->taxRepository->search($exact, $context)->getEntities()->first();

        if ($tax !== null) {
            $report->reused('tax');

            return ['id' => $tax->getId(), 'rate' => $tax->getTaxRate()];
        }

        $highest = new Criteria();
        $highest->addSorting(new FieldSorting('taxRate', FieldSorting::DESCENDING));
        $highest->setLimit(1);

        /** @var TaxEntity|null $fallback */
        $fallback = $this->taxRepository->search($highest, $context)->getEntities()->first();

        if ($fallback === null) {
            throw new DemoDataException('No tax rate configured. Create at least one tax rate before seeding demo data.');
        }

        $report->reused('tax');
        $report->note(\sprintf(
            'No %.2f%% tax rate exists; products fall back to the configured %.2f%% rate.',
            $preferredRate,
            $fallback->getTaxRate()
        ));

        return ['id' => $fallback->getId(), 'rate' => $fallback->getTaxRate()];
    }
}
