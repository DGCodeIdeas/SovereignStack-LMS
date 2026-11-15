<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Support\Fakes;

use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;

final class RecordingAuditLogger implements AuditLoggerInterface
{
    /**
     * @var list<array{
     *     eventType: string,
     *     actorUserId: null|string,
     *     organisationId: null|string,
     *     resourceType: string,
     *     resourceId: string,
     *     data: array<string, null|scalar>
     * }>
     */
    public array $records = [];

    public function record(
        string $eventType,
        ?string $actorUserId,
        ?string $organisationId,
        string $resourceType,
        string $resourceId,
        array $data = [],
    ): void {
        $this->records[] = [
            'eventType' => $eventType,
            'actorUserId' => $actorUserId,
            'organisationId' => $organisationId,
            'resourceType' => $resourceType,
            'resourceId' => $resourceId,
            'data' => $data,
        ];
    }

    public function hasEvent(string $eventType): bool
    {
        foreach ($this->records as $record) {
            if ($record['eventType'] === $eventType) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function eventTypes(): array
    {
        return array_map(static fn (array $record): string => $record['eventType'], $this->records);
    }
}
