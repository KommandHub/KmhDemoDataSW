<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Administration\Controller;

use Kommandhub\DemoData\Administration\Controller\DemoDataController;
use Kommandhub\DemoData\Blueprint\DemoBlueprint;
use Kommandhub\DemoData\MessageQueue\GenerateDemoDataMessage;
use Kommandhub\DemoData\Service\SeedStatusStore;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\RoutingException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class DemoDataControllerTest extends TestCase
{
    private MessageBusInterface&MockObject $bus;
    private SeedStatusStore&MockObject $status;
    private DemoDataController $controller;

    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->status = $this->createMock(SeedStatusStore::class);
        $this->controller = new DemoDataController($this->bus, $this->status);
    }

    public function testGenerateQueuesTheWorkAndAnswersImmediately(): void
    {
        $this->status->method('isRunning')->willReturn(false);
        $this->status->method('read')->willReturn(['state' => SeedStatusStore::STATE_RUNNING]);
        $this->status->expects($this->once())->method('markQueued');

        $dispatched = null;
        $this->bus->expects($this->once())->method('dispatch')->willReturnCallback(
            function (object $message) use (&$dispatched): Envelope {
                $dispatched = $message;

                return new Envelope($message);
            }
        );

        $response = $this->controller->generate($this->request([
            'channels' => ['fresh'],
            'perCategory' => 12,
            'withMedia' => false,
            'withOrders' => false,
        ]));

        // Accepted, not OK: the work has not happened yet.
        $this->assertSame(JsonResponse::HTTP_ACCEPTED, $response->getStatusCode());
        $this->assertInstanceOf(GenerateDemoDataMessage::class, $dispatched);
        $this->assertSame(['fresh'], $dispatched->channels);
        $this->assertSame(12, $dispatched->perCategory);
        $this->assertFalse($dispatched->withMedia);
        $this->assertFalse($dispatched->withOrders);
    }

    /**
     * Two seeders racing on the same deterministic ids would each see the
     * other's half-written rows as missing.
     */
    public function testGenerateRefusesWhileAnotherRunIsInFlight(): void
    {
        $this->status->method('isRunning')->willReturn(true);
        $this->status->method('read')->willReturn(['state' => SeedStatusStore::STATE_RUNNING]);
        $this->status->expects($this->never())->method('markQueued');
        $this->bus->expects($this->never())->method('dispatch');

        $response = $this->controller->generate($this->request([]));

        $this->assertSame(JsonResponse::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testGenerateDefaultsToEverything(): void
    {
        $this->status->method('isRunning')->willReturn(false);
        $this->status->method('read')->willReturn([]);

        $dispatched = null;
        $this->bus->method('dispatch')->willReturnCallback(
            function (object $message) use (&$dispatched): Envelope {
                $dispatched = $message;

                return new Envelope($message);
            }
        );

        $this->controller->generate($this->request([]));

        $this->assertInstanceOf(GenerateDemoDataMessage::class, $dispatched);
        $this->assertSame([], $dispatched->channels, 'no channels means every channel');
        $this->assertNull($dispatched->perCategory, 'no size means the blueprint default');
        $this->assertTrue($dispatched->withMedia);
        $this->assertTrue($dispatched->withOrders);
    }

    public function testGenerateRejectsAnUnknownChannel(): void
    {
        $this->status->method('isRunning')->willReturn(false);
        $this->bus->expects($this->never())->method('dispatch');

        $this->expectException(RoutingException::class);

        $this->controller->generate($this->request(['channels' => ['fresh', 'not-a-channel']]));
    }

    public function testGenerateRejectsANonsensicalCatalogueSize(): void
    {
        $this->status->method('isRunning')->willReturn(false);
        $this->bus->expects($this->never())->method('dispatch');

        $this->expectException(RoutingException::class);

        $this->controller->generate($this->request(['perCategory' => 0]));
    }

    public function testStatusDescribesWhatThePageNeedsToRenderItself(): void
    {
        $this->status->method('read')->willReturn(['state' => SeedStatusStore::STATE_IDLE]);

        /** @var array<string, mixed> $body */
        $body = json_decode((string)$this->controller->status()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(['state' => 'idle'], $body['status']);
        $this->assertSame(DemoBlueprint::PRODUCTS_PER_CATEGORY, $body['defaultPerCategory']);
        $this->assertCount(\count(DemoBlueprint::salesChannels()), $body['channels']);
        $this->assertSame(array_keys(DemoBlueprint::salesChannels()), array_column($body['channels'], 'key'));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(array $payload): Request
    {
        return new Request([], [], [], [], [], [], json_encode($payload, \JSON_THROW_ON_ERROR));
    }
}
