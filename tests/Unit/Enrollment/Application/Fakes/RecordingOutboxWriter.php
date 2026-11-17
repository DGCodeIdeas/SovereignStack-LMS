<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Enrollment\Application\Fakes;

use SovereignStack\Lms\Shared\Application\Outbox\OutboxWriterInterface;

final class RecordingOutboxWriter implements OutboxWriterInterface
{
    /**
     * @var list<array{
     *     eventType: string,
     *     aggregateType: string,
     *     aggregateId: string,
     *     organisationId: null|string,
     *     payload: array<string, mixed>,
     *     eventId: null|string
     * }>
     */
    public array $messages = [];

    public function write(
        string $eventType,
        string $aggregateType,
        string $aggregateId,
        ?string $organisationId,
        array $payload,
        ?string $eventId = null,
    ): void {
        $this->messages[] = [
            'eventType' => $eventType,
            'aggregateType' => $aggregateType,
            'aggregateId' => $aggregateId,
            'organisationId' => $organisationId,
            'payload' => $payload,
            'eventId' => $eventId,
        ];
    }

    public function hasEvent(string $eventType): bool
    {
        foreach ($this->messages as $message) {
            if ($message['eventType'] === $eventType) {
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
        return array_map(static fn (array $message): string => $message['eventType'], $this->messages);
    }
}
