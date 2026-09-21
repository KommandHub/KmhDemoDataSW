<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Administration\Controller;

use Kommandhub\DemoData\Blueprint\DemoBlueprint;
use Kommandhub\DemoData\MessageQueue\GenerateDemoDataMessage;
use Kommandhub\DemoData\Service\SeedStatusStore;
use Shopware\Core\Framework\Routing\RoutingException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The admin module's API.
 *
 * Generation is dispatched to the queue and answered immediately: the first
 * seed of an installation downloads four hundred photographs, which no browser
 * request should be asked to sit through. The page then polls `status` until
 * the handler records a result.
 *
 * The endpoints do no work themselves beyond validating what the page sent —
 * everything that matters lives in the same seeder the CLI command uses, so the
 * two cannot drift apart.
 */
#[Route(defaults: ['_routeScope' => ['api'], '_acl' => ['kmh_demo_data:generate']])]
class DemoDataController
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly SeedStatusStore $status
    ) {
    }

    #[Route(path: '/api/_action/kmh-demo-data/generate', name: 'api.action.kmh_demo_data.generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        // Refusing a second run is not a nicety: two seeders racing on the same
        // deterministic ids would each see the other's rows as missing.
        if ($this->status->isRunning()) {
            return new JsonResponse(
                ['status' => $this->status->read(), 'accepted' => false],
                JsonResponse::HTTP_CONFLICT
            );
        }

        $payload = $request->toArray();

        $this->status->markQueued();
        $this->bus->dispatch(new GenerateDemoDataMessage(
            $this->channels($payload),
            $this->perCategory($payload),
            (bool)($payload['withMedia'] ?? true),
            (bool)($payload['withOrders'] ?? true)
        ));

        return new JsonResponse(['status' => $this->status->read(), 'accepted' => true], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route(path: '/api/_action/kmh-demo-data/status', name: 'api.action.kmh_demo_data.status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return new JsonResponse([
            'status' => $this->status->read(),
            'channels' => $this->availableChannels(),
            'defaultPerCategory' => DemoBlueprint::PRODUCTS_PER_CATEGORY,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<int, string>
     */
    private function channels(array $payload): array
    {
        $requested = $payload['channels'] ?? [];

        if (!\is_array($requested)) {
            return [];
        }

        $known = array_keys(DemoBlueprint::salesChannels());
        $channels = array_values(array_intersect(array_filter($requested, '\is_string'), $known));

        // An unknown key is a bug in the caller, not something to guess at.
        if (\count($channels) !== \count($requested)) {
            throw RoutingException::invalidRequestParameter('channels');
        }

        return $channels;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function perCategory(array $payload): ?int
    {
        $value = $payload['perCategory'] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value) || (int)$value < 1) {
            throw RoutingException::invalidRequestParameter('perCategory');
        }

        return (int)$value;
    }

    /**
     * @return array<int, array{key: string, name: string}>
     */
    private function availableChannels(): array
    {
        $channels = [];

        foreach (DemoBlueprint::salesChannels() as $key => $definition) {
            $channels[] = ['key' => $key, 'name' => $definition['name']];
        }

        return $channels;
    }
}
