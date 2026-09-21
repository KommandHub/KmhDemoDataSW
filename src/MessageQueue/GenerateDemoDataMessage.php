<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\MessageQueue;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * A request from the admin to build the demo data.
 *
 * The work is queued rather than done in the request because the first seed of
 * an installation downloads four hundred photographs and takes minutes — far
 * past any sensible HTTP timeout. Everything the seeder needs travels in the
 * message, so the handler needs nothing from the session that sent it.
 */
class GenerateDemoDataMessage implements AsyncMessageInterface
{
    /**
     * @param array<int, string> $channels blueprint channel keys; empty means all of them
     */
    public function __construct(
        public readonly array $channels = [],
        public readonly ?int $perCategory = null,
        public readonly bool $withMedia = true,
        public readonly bool $withOrders = true
    ) {
    }
}
