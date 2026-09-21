<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Seeder;

use Kommandhub\DemoData\Seeder\LandingPageSeeder;
use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\DeterministicValueGenerator;
use Kommandhub\DemoData\Service\SeedReport;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class LandingPageSeederTest extends TestCase
{
    public function testSeedCreatesPageAndAssignsToOwnedRoot(): void
    {
        $cmsPageRepository = $this->createMock(EntityRepository::class);
        $categoryRepository = $this->createMock(EntityRepository::class);
        $seeder = new LandingPageSeeder($cmsPageRepository, $categoryRepository, new DemoIdGenerator(), new DeterministicValueGenerator());
        $context = Context::createDefaultContext();
        $report = new SeedReport();

        $definition = [
            'name' => 'Test Channel',
            'landing' => [
                'headline' => 'Welcome',
                'intro' => 'Test intro',
                'sliderTitle' => 'Featured Products',
                'featureText' => 'Feature this',
            ],
            'tree' => [['image' => 'img1.jpg'], ['image' => 'img2.jpg']],
        ];

        $cmsPageRepository->method('searchIds')->willReturn($this->idResult(null));
        $categoryRepository->method('searchIds')->willReturn($this->idResult(null));
        $cmsPageRepository->expects($this->once())->method('create');
        $categoryRepository->expects($this->once())->method('update');

        $seeder->seed($context, $report, 'flagship', $definition, 'root-id', true, ['img1.jpg' => 'media-1', 'img2.jpg' => 'media-2'], [[
            'leaf' => ['id' => 'l1'],
            'productIds' => ['p1', 'p2', 'p3'],
        ]]);

        $this->assertSame(1, $report->totalCreated());
    }

    public function testSeedSkipsWhenThereIsNoLandingDefinition(): void
    {
        $seeder = new LandingPageSeeder(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            new DemoIdGenerator(),
            new DeterministicValueGenerator()
        );

        $report = new SeedReport();
        $seeder->seed(Context::createDefaultContext(), $report, 'flagship', [], 'root-id', true, [], []);

        $this->assertSame(0, $report->totalCreated());
    }

    public function testSeedReusesExistingLandingPage(): void
    {
        $cmsPageRepository = $this->createMock(EntityRepository::class);
        $categoryRepository = $this->createMock(EntityRepository::class);
        $seeder = new LandingPageSeeder($cmsPageRepository, $categoryRepository, new DemoIdGenerator(), new DeterministicValueGenerator());

        $cmsPageRepository->method('searchIds')->willReturn($this->idResult('page-id'));
        $categoryRepository->method('searchIds')->willReturn($this->idResult('root-id'));
        $cmsPageRepository->expects($this->never())->method('create');

        $seeder->seed(Context::createDefaultContext(), new SeedReport(), 'flagship', [
            'name' => 'Test Channel',
            'landing' => ['headline' => 'Welcome'],
            'tree' => [],
        ], 'root-id', true, [], []);
    }

    public function testAssignSkipsAdoptedRootThatAlreadyHasLayout(): void
    {
        $cmsPageRepository = $this->createMock(EntityRepository::class);
        $categoryRepository = $this->createMock(EntityRepository::class);
        $seeder = new LandingPageSeeder($cmsPageRepository, $categoryRepository, new DemoIdGenerator(), new DeterministicValueGenerator());

        $categoryRepository->method('searchIds')->willReturnOnConsecutiveCalls($this->idResult(null));
        $method = (new \ReflectionClass($seeder))->getMethod('assign');
        $report = new SeedReport();

        $method->invoke($seeder, Context::createDefaultContext(), $report, 'root-id', false, 'page-id', ['headline' => 'Welcome']);

        $this->assertStringContainsString('built but not applied', implode("\n", $report->notes()));
    }

    public function testAssignReusesExistingAssignment(): void
    {
        $cmsPageRepository = $this->createMock(EntityRepository::class);
        $categoryRepository = $this->createMock(EntityRepository::class);
        $seeder = new LandingPageSeeder($cmsPageRepository, $categoryRepository, new DemoIdGenerator(), new DeterministicValueGenerator());

        $categoryRepository->method('searchIds')->willReturn($this->idResult('root-id'));
        $categoryRepository->expects($this->never())->method('update');
        $method = (new \ReflectionClass($seeder))->getMethod('assign');

        $method->invoke($seeder, Context::createDefaultContext(), new SeedReport(), 'root-id', true, 'page-id', ['headline' => 'Welcome']);
    }

    private function idResult(?string $firstId): IdSearchResult
    {
        $result = $this->createMock(IdSearchResult::class);
        $result->method('firstId')->willReturn($firstId);

        return $result;
    }
}
