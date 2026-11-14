<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Infrastructure\Messenger\Message;

final readonly class PrototypeMessage
{
    public function __construct(
        public string $eventId,
        public string $messageId,
        public string $payload,
    ) {}
}
