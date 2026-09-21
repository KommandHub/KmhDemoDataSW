<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Seeder;

use Kommandhub\DemoData\Blueprint\DemoBlueprint;
use Kommandhub\DemoData\Service\EntityResolver;
use Kommandhub\DemoData\Service\SeedReport;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * Manufacturers, matched on name so an installation that already carries
 * "Aurora Audio" keeps its own row and its own product links.
 */
class ManufacturerSeeder
{
    /**
     * @param EntityRepository<ProductManufacturerCollection> $manufacturerRepository
     */
    public function __construct(
        private readonly EntityRepository $manufacturerRepository,
        private readonly EntityResolver $resolver
    ) {
    }

    /**
     * @return array<string, string> blueprint key => manufacturer id
     */
    public function seed(Context $context, SeedReport $report): array
    {
        $creates = [];
        $updates = [];
        $ids = [];

        foreach (DemoBlueprint::manufacturers() as $key => $definition) {
            $resolved = $this->resolver->resolveByField(
                $this->manufacturerRepository,
                $context,
                $this->resolver->ids()->id('manufacturer', $key),
                'name',
                $definition['name']
            );

            $ids[$key] = $resolved->id;

            $payload = [
                'id' => $resolved->id,
                'name' => $definition['name'],
                'link' => $definition['link'],
                'description' => $definition['description'],
            ];

            if (!$resolved->exists) {
                $creates[] = $payload;
                $report->created('manufacturer');

                continue;
            }

            $resolved->adopted ? $report->adopted('manufacturer') : $report->reused('manufacturer');

            $enrichment = $this->resolver->enrichmentPayload($resolved, $payload, ['link', 'description']);

            if ($enrichment !== null) {
                $updates[] = $enrichment;
                $report->enriched('manufacturer');
            }
        }

        if ($creates !== []) {
            $this->manufacturerRepository->create($creates, $context);
        }

        if ($updates !== []) {
            $this->manufacturerRepository->update($updates, $context);
        }

        return $ids;
    }
}
