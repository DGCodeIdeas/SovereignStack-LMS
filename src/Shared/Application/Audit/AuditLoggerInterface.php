<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Application\Audit;

interface AuditLoggerInterface
{
    /**
     * @param array<string, null|scalar> $data
     */
    public function record(
        string $eventType,
        ?string $actorUserId,
        ?string $organisationId,
        string $resourceType,
        string $resourceId,
        array $data = [],
    ): void;
}
