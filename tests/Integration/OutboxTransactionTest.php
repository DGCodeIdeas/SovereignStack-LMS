<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration;

use Doctrine\DBAL\Connection;
use SovereignStack\Lms\Shared\Infrastructure\Messenger\Message\PrototypeMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class OutboxTransactionTest extends DatabaseIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->truncate('messenger_messages', 'messenger_prototype_events', 'messenger_prototype_failures');
    }

    public function testDispatchedMessageIsCommittedWithTheCallerTransaction(): void
    {
        $connection = $this->connection();
        $eventId = Uuid::v7()->toRfc4122();

        $connection->beginTransaction();

        try {
            $this->bus()->dispatch(new PrototypeMessage($eventId, Uuid::v7()->toRfc4122(), '{"transaction":"commit"}'));

            self::assertSame(1, $this->countMessages($connection));

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        self::assertSame(1, $this->countMessages($connection));
    }

    public function testDispatchedMessageIsRolledBackWithTheCallerTransaction(): void
    {
        $connection = $this->connection();
        $eventId = Uuid::v7()->toRfc4122();

        $connection->beginTransaction();

        try {
            $this->bus()->dispatch(new PrototypeMessage($eventId, Uuid::v7()->toRfc4122(), '{"transaction":"rollback"}'));

            self::assertSame(1, $this->countMessages($connection));

            $connection->rollBack();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        self::assertSame(0, $this->countMessages($connection));
    }

    private function countMessages(Connection $connection): int
    {
        return self::asInt($connection->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }
}
