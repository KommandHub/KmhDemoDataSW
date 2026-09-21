<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Seeder;

use Kommandhub\DemoData\Seeder\PropertySeeder;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\EntityResolver;
use Kommandhub\DemoData\Service\SeedReport;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class PropertySeederTest extends TestCase
{
    /**
     * Groups and options are each resolved and written as one batch, not one
     * query per group — the seeder runs against shops that already have a
     * "Colour" group, and asking about twelve of them individually is the N:1
     * this is meant to avoid.
     */
    public function testSeedResolvesGroupsAndOptionsInBatches(): void
    {
        $groupRepo = $this->createMock(EntityRepository::class);
        $optionRepo = $this->createMock(EntityRepository::class);
        $resolver = $this->createMock(EntityResolver::class);
        $ids = new DemoIdGenerator();
        $resolver->method('ids')->willReturn($ids);
        $resolver->method('enrichmentPayload')->willReturn(['id' => 'adopted-colour', 'description' => 'Top-up']);

        $seeder = new PropertySeeder($groupRepo, $optionRepo, $resolver);

        // One group already exists under a foreign id, matched by name.
        $adopted = new PropertyGroupEntity();
        $adopted->setId('adopted-colour');
        $adopted->setName('Colour');

        $groupSearches = 0;
        $groupRepo->method('search')->willReturnCallback(
            function () use ($adopted, &$groupSearches): EntitySearchResult {
                ++$groupSearches;

                return $this->searchResult(new PropertyGroupCollection([$adopted]));
            }
        );

        // One of its options exists too, so it is reused rather than recreated.
        $existingOption = new PropertyGroupOptionEntity();
        $existingOption->setId('existing-option');
        $existingOption->setName('Black');
        $existingOption->assign(['groupId' => 'adopted-colour']);

        $optionSearches = 0;
        $optionRepo->method('search')->willReturnCallback(
            function () use ($existingOption, &$optionSearches): EntitySearchResult {
                ++$optionSearches;

                return $this->searchResult(new PropertyGroupOptionCollection([$existingOption]));
            }
        );

        $groupCreates = 0;
        $groupRepo->method('create')->willReturnCallback(
            function () use (&$groupCreates): EntityWrittenContainerEvent {
                ++$groupCreates;

                return $this->writtenEvent();
            }
        );
        $optionCreates = 0;
        $optionRepo->method('create')->willReturnCallback(
            function () use (&$optionCreates): EntityWrittenContainerEvent {
                ++$optionCreates;

                return $this->writtenEvent();
            }
        );
        $groupRepo->method('update')->willReturnCallback(fn (): EntityWrittenContainerEvent => $this->writtenEvent());
        $groupRepo->expects($this->once())->method('update');

        $result = $seeder->seed(Context::createDefaultContext(), new SeedReport());

        $this->assertSame(1, $groupSearches, 'groups must be looked up in one query');
        $this->assertSame(1, $optionSearches, 'options must be looked up in one query');
        $this->assertSame(1, $groupCreates, 'missing groups are written in one call');
        $this->assertSame(1, $optionCreates, 'missing options are written in one call');

        // The adopted group keeps its own id, and its existing option is reused.
        $this->assertSame('adopted-colour', $result['colour']['id']);
        $this->assertSame('existing-option', $result['colour']['options']['Black']);
        $this->assertNotEmpty($result);
    }

    private function writtenEvent(): EntityWrittenContainerEvent
    {
        return EntityWrittenContainerEvent::createWithWrittenEvents([], Context::createDefaultContext(), []);
    }

    private function searchResult(PropertyGroupCollection|PropertyGroupOptionCollection $collection): EntitySearchResult
    {
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn($collection);

        return $result;
    }

    public function testColourHexReturnsNullForNonColourNames(): void
    {
        $resolver = $this->createMock(EntityResolver::class);
        $resolver->method('ids')->willReturn(new DemoIdGenerator());
        $seeder = new PropertySeeder(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $resolver
        );

        $method = (new \ReflectionClass($seeder))->getMethod('colourHex');

        $this->assertSame('#0b0b0b', $method->invoke($seeder, 'Black'));
        $this->assertNull($method->invoke($seeder, 'Cotton'));
    }
}
