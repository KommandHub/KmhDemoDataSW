<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Seeder;

use Kommandhub\DemoData\Blueprint\DemoBlueprint;
use Kommandhub\DemoData\Service\EntityResolver;
use Kommandhub\DemoData\Service\SeedReport;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\Tag\TagCollection;

/**
 * Tags. Names are unique in Shopware, so the natural key is the only sensible
 * match — creating a second "Best Seller" would be rejected by the database
 * anyway.
 */
class TagSeeder
{
    /**
     * @param EntityRepository<TagCollection> $tagRepository
     */
    public function __construct(
        private readonly EntityRepository $tagRepository,
        private readonly EntityResolver $resolver
    ) {
    }

    /**
     * @return array<int, string> tag ids
     */
    public function seed(Context $context, SeedReport $report): array
    {
        $creates = [];
        $ids = [];

        foreach (DemoBlueprint::tags() as $name) {
            $resolved = $this->resolver->resolveByField(
                $this->tagRepository,
                $context,
                $this->resolver->ids()->id('tag', $name),
                'name',
                $name
            );

            $ids[] = $resolved->id;

            if ($resolved->exists) {
                $resolved->adopted ? $report->adopted('tag') : $report->reused('tag');

                continue;
            }

            $creates[] = ['id' => $resolved->id, 'name' => $name];
            $report->created('tag');
        }

        if ($creates !== []) {
            $this->tagRepository->create($creates, $context);
        }

        return $ids;
    }
}
