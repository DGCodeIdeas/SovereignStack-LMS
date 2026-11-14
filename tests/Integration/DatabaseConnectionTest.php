<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class DatabaseConnectionTest extends DatabaseIntegrationTestCase
{
    public function testConnectionAndServerVersion(): void
    {
        $version = $this->connection()->fetchOne('SELECT VERSION()');

        self::assertIsString($version);
        self::assertNotSame('', $version);
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', $version);
    }

    public function testTransactionCommitAndRollback(): void
    {
        $connection = $this->connection();
        $slug = 'org-'.bin2hex(random_bytes(8));

        $connection->beginTransaction();
        $this->insertOrganisation($connection, $slug);
        $connection->rollBack();

        self::assertSame(
            0,
            $this->fetchInt('SELECT COUNT(*) FROM organisations WHERE slug = :slug', ['slug' => $slug]),
        );

        $connection->beginTransaction();
        $this->insertOrganisation($connection, $slug);
        $connection->commit();

        self::assertSame(
            1,
            $this->fetchInt('SELECT COUNT(*) FROM organisations WHERE slug = :slug', ['slug' => $slug]),
        );
    }

    public function testDatetimeMicroseconds(): void
    {
        $connection = $this->connection();
        $id = $this->insertOrganisation($connection, 'org-'.bin2hex(random_bytes(8)), '2026-11-14 12:34:56.123456');

        $stored = $connection->fetchOne('SELECT created_at FROM organisations WHERE id = :id', ['id' => $id]);

        self::assertSame('2026-11-14 12:34:56.123456', $stored);
    }

    public function testJsonColumnRoundTrip(): void
    {
        $connection = $this->connection();
        $id = $this->insertOutbox($connection);

        $value = $connection->fetchOne("SELECT JSON_EXTRACT(payload, '$.n') FROM outbox_messages WHERE id = :id", [
            'id' => $id,
        ]);
        $valid = $connection->fetchOne('SELECT JSON_VALID(payload) FROM outbox_messages WHERE id = :id', [
            'id' => $id,
        ]);

        self::assertSame(7, self::asInt($value));
        self::assertSame(1, self::asInt($valid));
    }

    public function testForUpdateSkipLockedWithTwoConnections(): void
    {
        $connection = $this->connection();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->insertOutbox($connection, 'pending', $now->format('Y-m-d H:i:s.u'));
        $this->insertOutbox($connection, 'pending', $now->modify('+1 second')->format('Y-m-d H:i:s.u'));
        $this->insertOutbox($connection, 'pending', $now->modify('+2 seconds')->format('Y-m-d H:i:s.u'));

        $second = $this->secondConnection();

        try {
            $connection->beginTransaction();
            $first = $connection->fetchOne(
                "SELECT id FROM outbox_messages WHERE status = 'pending' ORDER BY available_at LIMIT 1 FOR UPDATE SKIP LOCKED",
            );

            $second->beginTransaction();
            $secondRow = $second->fetchOne(
                "SELECT id FROM outbox_messages WHERE status = 'pending' ORDER BY available_at LIMIT 1 FOR UPDATE SKIP LOCKED",
            );

            self::assertIsString($first);
            self::assertIsString($secondRow);
            self::assertNotSame($first, $secondRow);
        } finally {
            $connection->rollBack();
            $second->rollBack();
            $second->close();
        }
    }

    public function testUtcTimestampStorage(): void
    {
        $connection = $this->connection();
        $id = $this->insertOrganisation($connection, 'org-'.bin2hex(random_bytes(8)), '2026-01-02 03:04:05.000001');

        $stored = $connection->fetchOne('SELECT created_at FROM organisations WHERE id = :id', ['id' => $id]);

        self::assertSame('2026-01-02 03:04:05.000001', $stored);
    }

    private function insertOrganisation(Connection $connection, string $slug, ?string $createdAt = null): string
    {
        $id = Uuid::v7()->toRfc4122();
        $timestamp = $createdAt ?? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

        $connection->insert('organisations', [
            'id' => $id,
            'name' => 'Test Organisation',
            'slug' => $slug,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return $id;
    }

    private function insertOutbox(Connection $connection, string $status = 'pending', ?string $availableAt = null): string
    {
        $id = Uuid::v7()->toRfc4122();
        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

        $connection->insert('outbox_messages', [
            'id' => $id,
            'event_id' => Uuid::v7()->toRfc4122(),
            'event_type' => 'test.event',
            'aggregate_type' => 'TestAggregate',
            'aggregate_id' => Uuid::v7()->toRfc4122(),
            'payload' => '{"source":"phase-1","n":7}',
            'occurred_at' => $timestamp,
            'available_at' => $availableAt ?? $timestamp,
            'status' => $status,
            'created_at' => $timestamp,
        ]);

        return $id;
    }
}
