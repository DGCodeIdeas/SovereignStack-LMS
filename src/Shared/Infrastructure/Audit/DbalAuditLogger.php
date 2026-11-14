<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Infrastructure\Audit;

use Doctrine\DBAL\Connection;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;
use SovereignStack\Lms\Shared\Infrastructure\Correlation\CorrelationIdHolder;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DatabaseValue;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DateTimeCodec;

final readonly class DbalAuditLogger implements AuditLoggerInterface
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        private UuidGeneratorInterface $uuid,
        private CorrelationIdHolder $correlation,
    ) {}

    public function record(
        string $eventType,
        ?string $actorUserId,
        ?string $organisationId,
        string $resourceType,
        string $resourceId,
        array $data = [],
    ): void {
        $correlationId = $this->correlation->get();

        if (null === $correlationId || 1 !== preg_match(self::UUID_PATTERN, $correlationId)) {
            $correlationId = $this->uuid->generate();
        }

        $this->connection->insert('audit_events', [
            'id' => $this->uuid->generate(),
            'event_type' => $eventType,
            'actor_user_id' => $actorUserId,
            'organisation_id' => $organisationId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'event_data' => DatabaseValue::encodeJson($data),
            'ip_address' => null,
            'occurred_at' => DateTimeCodec::toDatabase($this->clock->now()),
            'correlation_id' => $correlationId,
        ]);
    }
}
