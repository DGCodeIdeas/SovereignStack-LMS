<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Infrastructure\Outbox;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use SovereignStack\Lms\Shared\Application\Outbox\OutboxWriterInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DatabaseValue;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DateTimeCodec;

final readonly class DbalOutboxWriter implements OutboxWriterInterface
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        private UuidGeneratorInterface $uuid,
    ) {}

    public function write(
        string $eventType,
        string $aggregateType,
        string $aggregateId,
        ?string $organisationId,
        array $payload,
        ?string $eventId = null,
    ): void {
        $now = DateTimeCodec::toDatabase($this->clock->now());

        $this->connection->executeStatement(
            'INSERT INTO outbox_messages (
                id, event_id, event_type, aggregate_type, aggregate_id, organisation_id,
                payload, payload_version, occurred_at, available_at, status, attempts, created_at
             ) VALUES (
                :id, :event_id, :event_type, :aggregate_type, :aggregate_id, :organisation_id,
                :payload, :payload_version, :occurred_at, :available_at, :status, :attempts, :created_at
             )
             ON DUPLICATE KEY UPDATE id = id',
            [
                'id' => $this->uuid->generate(),
                'event_id' => $eventId ?? $this->uuid->generate(),
                'event_type' => $eventType,
                'aggregate_type' => $aggregateType,
                'aggregate_id' => $aggregateId,
                'organisation_id' => $organisationId,
                'payload' => DatabaseValue::encodeJson($payload),
                'payload_version' => 1,
                'occurred_at' => $now,
                'available_at' => $now,
                'status' => 'pending',
                'attempts' => 0,
                'created_at' => $now,
            ],
            [
                'payload_version' => ParameterType::INTEGER,
                'attempts' => ParameterType::INTEGER,
            ],
        );
    }
}
