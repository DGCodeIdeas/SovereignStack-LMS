<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration;

use SovereignStack\Lms\Shared\Infrastructure\Messenger\Message\FailingPrototypeMessage;
use SovereignStack\Lms\Shared\Infrastructure\Messenger\Message\PrototypeMessage;
use Symfony\Component\Process\Process;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class MessengerDoctrineTransportTest extends DatabaseIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->truncate('messenger_messages', 'messenger_prototype_events', 'messenger_prototype_failures');
    }

    public function testDispatchPersistsMessageOnAsyncQueue(): void
    {
        $eventId = Uuid::v7()->toRfc4122();
        $messageId = Uuid::v7()->toRfc4122();

        $this->bus()->dispatch(new PrototypeMessage($eventId, $messageId, '{"prototype":"persisted"}'));

        $connection = $this->connection();

        self::assertSame(
            1,
            $this->fetchInt('SELECT COUNT(*) FROM messenger_messages WHERE queue_name = ?', ['async']),
        );

        $row = $connection->fetchAssociative(
            'SELECT body, queue_name, delivered_at FROM messenger_messages WHERE queue_name = ?',
            ['async'],
        );

        self::assertNotFalse($row);
        self::assertNull($row['delivered_at']);
        self::assertStringContainsString($eventId, self::bodyAsString($row['body']));
    }

    public function testWorkerConsumesRetriesAndRoutesFailureToFailureTransport(): void
    {
        $successEventId = Uuid::v7()->toRfc4122();
        $failureEventId = Uuid::v7()->toRfc4122();

        $this->bus()->dispatch(new PrototypeMessage($successEventId, Uuid::v7()->toRfc4122(), '{"prototype":"success"}'));
        $this->bus()->dispatch(new FailingPrototypeMessage($failureEventId, Uuid::v7()->toRfc4122(), '{"prototype":"failure"}'));

        $process = $this->runWorker();

        self::assertTrue(
            $process->isSuccessful(),
            "Worker failed (exit code {$process->getExitCode()}).\nSTDOUT:\n{$process->getOutput()}\nSTDERR:\n{$process->getErrorOutput()}",
        );

        $connection = $this->connection();

        self::assertSame(
            1,
            $this->fetchInt(
                'SELECT COUNT(*) FROM messenger_prototype_events WHERE event_id = ?',
                [$successEventId],
            ),
        );

        self::assertSame(
            1,
            $this->fetchInt(
                'SELECT COUNT(*) FROM messenger_messages WHERE queue_name = ? AND body LIKE ?',
                ['failed', '%'.$failureEventId.'%'],
            ),
        );

        $attempts = $this->fetchInt(
            'SELECT COUNT(*) FROM messenger_prototype_failures WHERE event_id = ?',
            [$failureEventId],
        );

        self::assertGreaterThanOrEqual(2, $attempts);
        self::assertLessThanOrEqual(3, $attempts);

        $asyncBodies = array_map(
            static fn (array $row): string => self::bodyAsString($row['body']),
            $connection->fetchAllAssociative("SELECT body FROM messenger_messages WHERE queue_name = 'async'"),
        );

        foreach ($asyncBodies as $body) {
            self::assertStringNotContainsString($successEventId, $body);
            self::assertStringNotContainsString($failureEventId, $body);
        }
    }

    public function testHandlerIsIdempotentForDuplicateEventId(): void
    {
        $eventId = Uuid::v7()->toRfc4122();

        $this->bus()->dispatch(new PrototypeMessage($eventId, Uuid::v7()->toRfc4122(), '{"prototype":"first"}'));
        $this->bus()->dispatch(new PrototypeMessage($eventId, Uuid::v7()->toRfc4122(), '{"prototype":"second"}'));

        $process = $this->runWorker();

        self::assertTrue(
            $process->isSuccessful(),
            "Worker failed (exit code {$process->getExitCode()}).\nSTDOUT:\n{$process->getOutput()}\nSTDERR:\n{$process->getErrorOutput()}",
        );

        $connection = $this->connection();

        self::assertSame(
            1,
            $this->fetchInt(
                'SELECT COUNT(*) FROM messenger_prototype_events WHERE event_id = ?',
                [$eventId],
            ),
        );

        self::assertSame(
            0,
            $this->fetchInt("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'async'"),
        );
    }

    public function testTransportTableIsCreatedByMigrationAndNotByAutoSetup(): void
    {
        self::assertCount(
            1,
            $this->connection()->fetchAllAssociative("SHOW TABLES LIKE 'messenger_messages'"),
        );
    }

    private function runWorker(): Process
    {
        $env = array_filter($_SERVER, 'is_string') + array_filter($_ENV, 'is_string');
        $env['APP_ENV'] = 'test';
        $env['APP_DEBUG'] = '1';

        $process = new Process(
            [PHP_BINARY, 'bin/console', 'messenger:consume', 'async', '--limit=20', '--time-limit=5', '--no-interaction', '-vv'],
            \dirname(__DIR__, 2),
            $env,
            null,
            120.0,
        );

        $process->run();

        return $process;
    }

    private static function bodyAsString(mixed $body): string
    {
        if (\is_resource($body)) {
            $contents = stream_get_contents($body);
            self::assertNotFalse($contents);

            return $contents;
        }

        if (\is_scalar($body) || $body instanceof \Stringable) {
            return (string) $body;
        }

        self::fail('Expected a stringable message body, got '.get_debug_type($body).'.');
    }
}
