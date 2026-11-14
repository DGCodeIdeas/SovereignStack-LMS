<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Infrastructure\Messenger\Handler;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Infrastructure\Messenger\Message\PrototypeMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PrototypeMessageHandler
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        #[Autowire('%app.prototype_event_table%')]
        private string $prototypeEventTable,
    ) {}

    public function __invoke(PrototypeMessage $message): void
    {
        $this->connection->executeStatement(
            'INSERT IGNORE INTO '.$this->prototypeEventTable.' (event_id, message_id, payload, processed_at) VALUES (?, ?, ?, ?)',
            [
                $message->eventId,
                $message->messageId,
                $message->payload,
                $this->clock->now()->format('Y-m-d H:i:s.u'),
            ],
        );

        $this->logger->info('Prototype event persisted', [
            'event_id' => $message->eventId,
            'message_id' => $message->messageId,
        ]);
    }
}
