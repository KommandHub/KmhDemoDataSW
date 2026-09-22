<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Administration\Controller;

use Kommandhub\DemoData\Administration\Controller\DemoDataController;
use Kommandhub\DemoData\Blueprint\DemoBlueprint;
use Kommandhub\DemoData\MessageQueue\GenerateDemoDataMessage;
use Kommandhub\DemoData\Service\DemoUserAccount;
use Kommandhub\DemoData\Service\DemoUserProvisioner;
use Kommandhub\DemoData\Service\SeedStatusStore;
use Shopware\Core\Framework\Context;
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
    private DemoUserProvisioner&MockObject $users;
    private DemoDataController $controller;

    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->status = $this->createMock(SeedStatusStore::class);
        $this->users = $this->createMock(DemoUserProvisioner::class);
        $this->controller = new DemoDataController($this->bus, $this->status, $this->users);
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

    public function testGenerateTreatsMalformedChannelsAsNoChannelFilter(): void
    {
        $this->status->method('isRunning')->willReturn(false);
        $this->status->method('read')->willReturn([]);
        $this->bus->expects($this->once())->method('dispatch')->willReturnCallback(
            static fn (object $message): Envelope => new Envelope($message)
        );

        $response = $this->controller->generate($this->request([
            'channels' => 'fresh',
            'perCategory' => '',
        ]));

        $this->assertSame(JsonResponse::HTTP_ACCEPTED, $response->getStatusCode());
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

        $this->users->method('describe')->willReturn(
            ['exists' => true, 'email' => 'demo@example.com', 'username' => 'demo', 'active' => true, 'privileges' => 412]
        );

        /** @var array<string, mixed> $body */
        $body = json_decode(
            (string)$this->controller->status(Context::createDefaultContext())->getContent(),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        $this->assertSame(['state' => 'idle'], $body['status']);
        $this->assertSame(DemoBlueprint::PRODUCTS_PER_CATEGORY, $body['defaultPerCategory']);
        $this->assertCount(\count(DemoBlueprint::salesChannels()), $body['channels']);
        $this->assertSame(array_keys(DemoBlueprint::salesChannels()), array_column($body['channels'], 'key'));
        $this->assertSame('demo@example.com', $body['demoUser']['email']);
    }

    public function testDemoUserReturnsTheGeneratedPasswordOnce(): void
    {
        $this->users->method('provision')->willReturn(
            DemoUserAccount::created('user-id', 'demo@example.com', 'demo', 'a-generated-password', 412)
        );

        /** @var array<string, mixed> $body */
        $body = json_decode(
            (string)$this->controller->demoUser($this->request([]), Context::createDefaultContext())->getContent(),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        $this->assertTrue($body['created']);
        $this->assertSame('a-generated-password', $body['password']);
        $this->assertSame(412, $body['privileges']);
    }

    /**
     * A blank field from the form means "use the default", not a user called "".
     */
    public function testDemoUserTreatsBlankFieldsAsAbsent(): void
    {
        $passed = [];
        $this->users->method('provision')->willReturnCallback(
            function (Context $context, ?string $email, ?string $username, ?string $password, bool $rotate) use (&$passed): DemoUserAccount {
                $passed = [$email, $username, $password, $rotate];

                return DemoUserAccount::created('user-id', 'demo@example.com', 'demo', 'pw', 1);
            }
        );

        $this->controller->demoUser(
            $this->request(['email' => '   ', 'username' => '', 'rotatePassword' => true]),
            Context::createDefaultContext()
        );

        $this->assertSame([null, null, null, true], $passed);
    }

    /**
     * A real value gets trimmed and passed through as the chosen login,
     * distinct from the blank case which falls back to the default.
     */
    public function testDemoUserPassesThroughTrimmedFieldsWhenProvided(): void
    {
        $passed = [];
        $this->users->method('provision')->willReturnCallback(
            function (Context $context, ?string $email, ?string $username, ?string $password, bool $rotate) use (&$passed): DemoUserAccount {
                $passed = [$email, $username];

                return DemoUserAccount::created('user-id', 'demo@example.com', 'demo', 'pw', 1);
            }
        );

        $this->controller->demoUser(
            $this->request(['email' => '  custom@example.com  ', 'username' => '  custom-user  ']),
            Context::createDefaultContext()
        );

        $this->assertSame(['custom@example.com', 'custom-user'], $passed);
    }

    /**
     * The account exists and this plugin did not create it: a conflict, not a
     * failure, and the page says something different about each.
     */
    public function testDemoUserAnswersConflictWhenTheLoginBelongsToSomebodyElse(): void
    {
        $this->users->method('provision')->willReturn(
            DemoUserAccount::refused('other-id', 'demo@example.com', 'demo')
        );

        $response = $this->controller->demoUser($this->request([]), Context::createDefaultContext());

        $this->assertSame(JsonResponse::HTTP_CONFLICT, $response->getStatusCode());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(array $payload): Request
    {
        return new Request([], [], [], [], [], [], json_encode($payload, \JSON_THROW_ON_ERROR));
    }
}
