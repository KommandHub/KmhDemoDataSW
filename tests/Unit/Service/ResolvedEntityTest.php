<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Service;

use Kommandhub\DemoData\Service\ResolvedEntity;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;

class ResolvedEntityTest extends TestCase
{
    public function testOwnedExposesCurrentValues(): void
    {
        $entity = new CategoryEntity();
        $entity->setId('cat-id');
        $entity->setName('Footwear');

        $resolved = ResolvedEntity::owned('cat-id', $entity);

        $this->assertSame('Footwear', $resolved->current('name'));
        $this->assertFalse($resolved->isEmpty('name'));
    }

    public function testCurrentReturnsNullForMissingFieldOrMissingEntity(): void
    {
        $entity = new CategoryEntity();
        $entity->setId('cat-id');

        $resolved = ResolvedEntity::adopted('cat-id', $entity);

        $this->assertNull($resolved->current('doesNotExist'));
        $this->assertNull(ResolvedEntity::missing('missing-id')->current('name'));
    }

    public function testIsEmptyTreatsEmptyArrayAndEmptyStringAsEmpty(): void
    {
        $entity = new class() extends CategoryEntity {
            public function exposeSet(string $property, mixed $value): void
            {
                $this->assign([$property => $value]);
            }
        };
        $entity->setId('cat-id');
        $entity->exposeSet('customFields', []);
        $entity->exposeSet('description', '');

        $resolved = ResolvedEntity::owned('cat-id', $entity);

        $this->assertTrue($resolved->isEmpty('customFields'));
        $this->assertTrue($resolved->isEmpty('description'));
    }
}
