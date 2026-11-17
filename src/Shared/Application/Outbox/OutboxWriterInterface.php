<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Application\Outbox;

interface OutboxWriterInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function write(
        string $eventType,
        string $aggregateType,
        string $aggregateId,
        ?string $organisationId,
        array $payload,
        ?string $eventId = null,
    ): void;
}
