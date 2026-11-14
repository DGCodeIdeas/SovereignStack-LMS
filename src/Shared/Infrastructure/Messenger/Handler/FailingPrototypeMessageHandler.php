<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Infrastructure\Messenger\Handler;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Infrastructure\Messenger\Message\FailingPrototypeMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FailingPrototypeMessageHandler
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        #[Autowire('%app.prototype_failure_table%')]
        private string $prototypeFailureTable,
    ) {}

    public function __invoke(FailingPrototypeMessage $message): void
    {
        $previousAttempts = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM '.$this->prototypeFailureTable.' WHERE event_id = ?',
            [$message->eventId],
        );

        if (!\is_numeric($previousAttempts)) {
            throw new \RuntimeException('Unable to determine the previous attempt count for '.$message->eventId);
        }

        $attemptNo = 1 + (int) $previousAttempts;

        $this->connection->executeStatement(
            'INSERT INTO '.$this->prototypeFailureTable.' (event_id, attempt_no, failed_at, error) VALUES (?, ?, ?, ?)',
            [
                $message->eventId,
                $attemptNo,
                $this->clock->now()->format('Y-m-d H:i:s.u'),
                'Intentional prototype failure',
            ],
        );

        $this->logger->warning('Prototype failure recorded', [
            'event_id' => $message->eventId,
            'attempt_no' => $attemptNo,
        ]);

        throw new \RuntimeException('Intentional prototype failure for '.$message->eventId);
    }
}
