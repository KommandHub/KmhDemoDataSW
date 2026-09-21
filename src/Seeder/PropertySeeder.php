<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Seeder;

use Kommandhub\DemoData\Blueprint\DemoBlueprint;
use Kommandhub\DemoData\Service\EntityResolver;
use Kommandhub\DemoData\Service\ResolvedEntity;
use Kommandhub\DemoData\Service\SeedReport;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;

/**
 * Property groups and their options.
 *
 * Options are resolved *inside* the resolved group, which matters when the group
 * was adopted: a shop that already has a "Colour" group with "Black" and "White"
 * gets the seven missing options added to that group rather than a second colour
 * group standing next to it.
 */
class PropertySeeder
{
    /**
     * @param EntityRepository<PropertyGroupCollection> $propertyGroupRepository
     * @param EntityRepository<PropertyGroupOptionCollection> $propertyGroupOptionRepository
     */
    public function __construct(
        private readonly EntityRepository $propertyGroupRepository,
        private readonly EntityRepository $propertyGroupOptionRepository,
        private readonly EntityResolver $resolver
    ) {
    }

    /**
     * @return array<string, array{id: string, options: array<string, string>}> blueprint key => group id + option name => option id
     */
    public function seed(Context $context, SeedReport $report): array
    {
        $definitions = DemoBlueprint::propertyGroups();

        // Resolved as a batch. One query for every group beats one query per
        // group, and the seeder runs against installations that already have a
        // "Colour" group to find.
        $existing = $this->existingGroups($context, $definitions);

        $groups = [];
        $ids = [];
        $creates = [];
        $updates = [];

        foreach ($definitions as $key => $definition) {
            $deterministicId = $this->resolver->ids()->id('property-group', $key);
            $match = $existing[$deterministicId] ?? $existing[mb_strtolower($definition['name'])] ?? null;

            $resolved = $match === null
                ? ResolvedEntity::missing($deterministicId)
                : ($match->getId() === $deterministicId
                    ? ResolvedEntity::owned($deterministicId, $match)
                    : ResolvedEntity::adopted($match->getId(), $match));

            $groupId = $resolved->id;

            $payload = [
                'id' => $groupId,
                'name' => $definition['name'],
                'description' => $definition['description'],
                'displayType' => $definition['displayType'],
                'sortingType' => $definition['sortingType'],
                'filterable' => true,
                'visibleOnProductDetailPage' => true,
            ];

            if (!$resolved->exists) {
                $creates[] = $payload;
                $report->created('property_group');
            } else {
                $resolved->adopted ? $report->adopted('property_group') : $report->reused('property_group');

                $enrichment = $this->resolver->enrichmentPayload($resolved, $payload, ['description']);

                if ($enrichment !== null) {
                    $updates[] = $enrichment;
                    $report->enriched('property_group');
                }
            }

            $groups[$key] = ['id' => $groupId, 'options' => []];
            $ids[$key] = $groupId;
        }

        if ($creates !== []) {
            $this->propertyGroupRepository->create($creates, $context);
        }

        if ($updates !== []) {
            $this->propertyGroupRepository->update($updates, $context);
        }

        // Options come after the groups exist, so every option has a group to
        // hang off even on a first run. Both the read and the write are done
        // once for every group rather than once per group.
        $stored = $this->existingOptions($context, array_values($ids));
        $optionCreates = [];

        foreach ($definitions as $key => $definition) {
            $groupId = $ids[$key];
            $resolvedOptions = [];
            $position = 0;

            foreach ($definition['options'] as $name) {
                ++$position;
                $known = $stored[$groupId][$name] ?? null;

                if ($known !== null) {
                    $resolvedOptions[$name] = $known;
                    $report->reused('property_group_option');

                    continue;
                }

                $optionId = $this->resolver->ids()->id('property-option', $key, $name);
                $resolvedOptions[$name] = $optionId;

                $optionCreates[] = [
                    'id' => $optionId,
                    'groupId' => $groupId,
                    'name' => $name,
                    'position' => $position,
                    'colorHexCode' => $this->colourHex($name),
                ];

                $report->created('property_group_option');
            }

            $groups[$key] = ['id' => $groupId, 'options' => $resolvedOptions];
        }

        if ($optionCreates !== []) {
            $this->propertyGroupOptionRepository->create($optionCreates, $context);
        }

        return $groups;
    }

    /**
     * Every property group this blueprint might match, keyed both by the id we
     * would mint and by lower-cased name, so a group somebody else created is
     * found by either route.
     *
     * @param array<string, array{name: string, description: string, displayType: string, sortingType: string, options: array<int, string>}> $definitions
     *
     * @return array<string, PropertyGroupEntity>
     */
    private function existingGroups(Context $context, array $definitions): array
    {
        $ids = [];
        $names = [];

        foreach ($definitions as $key => $definition) {
            $ids[] = $this->resolver->ids()->id('property-group', $key);
            $names[] = $definition['name'];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new OrFilter([
            new EqualsAnyFilter('id', $ids),
            new EqualsAnyFilter('name', $names),
        ]));
        $criteria->setLimit(500);

        $found = [];
        $matches = $this->propertyGroupRepository->search($criteria, $context)->getEntities();

        foreach ($matches as $group) {
            /** @var PropertyGroupEntity $group */
            $found[$group->getId()] = $group;
            $name = $group->getName();

            if ($name !== null) {
                $found[mb_strtolower($name)] ??= $group;
            }
        }

        return $found;
    }

    /**
     * Existing options for every group at once, partitioned by group.
     *
     * @param array<int, string> $groupIds
     *
     * @return array<string, array<string, string>> group id => option name => option id
     */
    private function existingOptions(Context $context, array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('groupId', array_values($groupIds)));
        $criteria->setLimit(2000);

        $options = [];
        $found = $this->propertyGroupOptionRepository->search($criteria, $context)->getEntities();

        foreach ($found as $option) {
            /** @var PropertyGroupOptionEntity $option */
            $name = $option->getName();
            // A partially hydrated option has no group to file it under, and
            // reading the typed property before it is set is a fatal, not a null.
            $groupId = $option->getVars()['groupId'] ?? null;

            if ($name !== null && \is_string($groupId)) {
                $options[$groupId][$name] = $option->getId();
            }
        }

        return $options;
    }

    /**
     * Colour swatches only make sense for colour names; everything else stays
     * null so Shopware renders it as plain text.
     */
    private function colourHex(string $name): ?string
    {
        return match ($name) {
            'Black' => '#0b0b0b',
            'White' => '#ffffff',
            'Graphite' => '#4a4a4a',
            'Silver' => '#c0c4c8',
            'Midnight Blue' => '#1b2a4a',
            'Forest Green' => '#2f4f3a',
            'Crimson' => '#9b1b30',
            'Sand' => '#d9c7a3',
            'Terracotta' => '#b4572d',
            default => null,
        };
    }
}
