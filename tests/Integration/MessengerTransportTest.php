<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration;

final class MessengerTransportTest extends DatabaseIntegrationTestCase
{
    public function testMessengerTransportTableCanBeCreated(): void
    {
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE messenger_messages (
                id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                body LONGTEXT NOT NULL,
                headers LONGTEXT NOT NULL,
                queue_name VARCHAR(190) NOT NULL,
                created_at DATETIME(6) NOT NULL,
                available_at DATETIME(6) NOT NULL,
                delivered_at DATETIME(6) NULL,
                INDEX idx_available (available_at),
                INDEX idx_queue (queue_name, available_at)
            )',
        );
        $this->addToAssertionCount(1);
    }

    public function testMessageCanBePersisted(): void
    {
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE messenger_messages (
                id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                body LONGTEXT NOT NULL,
                headers LONGTEXT NOT NULL,
                queue_name VARCHAR(190) NOT NULL,
                created_at DATETIME(6) NOT NULL,
                available_at DATETIME(6) NOT NULL,
                delivered_at DATETIME(6) NULL,
                INDEX idx_available (available_at),
                INDEX idx_queue (queue_name, available_at)
            )',
        );

        $this->connection->executeStatement(
            "INSERT INTO messenger_messages (body, headers, queue_name, created_at, available_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
            ['{"message":"test"}', '{}', 'default'],
        );

        $count = (int) $this->connection->executeQuery(
            'SELECT COUNT(*) FROM messenger_messages',
        )->fetchOne();

        $this->assertSame(1, $count, 'One message must be persisted');
    }

    public function testMessageCanBeClaimedWithSkipLocked(): void
    {
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE messenger_messages (
                id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                body LONGTEXT NOT NULL,
                headers LONGTEXT NOT NULL,
                queue_name VARCHAR(190) NOT NULL,
                created_at DATETIME(6) NOT NULL,
                available_at DATETIME(6) NOT NULL,
                delivered_at DATETIME(6) NULL,
                INDEX idx_available (available_at),
                INDEX idx_queue (queue_name, available_at)
            )',
        );

        $this->connection->executeStatement(
            "INSERT INTO messenger_messages (body, headers, queue_name, created_at, available_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
            ['{"message":"test1"}', '{}', 'default'],
        );
        $this->connection->executeStatement(
            "INSERT INTO messenger_messages (body, headers, queue_name, created_at, available_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
            ['{"message":"test2"}', '{}', 'default'],
        );

        $this->connection->beginTransaction();
        try {
            $result = $this->connection->executeQuery(
                "SELECT id FROM messenger_messages
                 WHERE available_at <= UTC_TIMESTAMP(6)
                 ORDER BY available_at
                 LIMIT 1
                 FOR UPDATE SKIP LOCKED",
            );
            $claimedId = (int) $result->fetchOne();

            $this->assertGreaterThan(0, $claimedId, 'A message must be claimed');

            $this->connection->executeStatement(
                'UPDATE messenger_messages SET delivered_at = UTC_TIMESTAMP(6) WHERE id = ?',
                [$claimedId],
            );

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            $this->fail('Message claim with SKIP LOCKED failed: ' . $e->getMessage());
        }
    }

    public function testOutboxSchemaWorksOnCurrentEngine(): void
    {
        $engine = $this->getDatabaseEngine();

        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE outbox_messages (
                id CHAR(36) NOT NULL PRIMARY KEY,
                event_id CHAR(36) NOT NULL,
                event_type VARCHAR(128) NOT NULL,
                aggregate_type VARCHAR(64) NOT NULL,
                aggregate_id CHAR(36) NOT NULL,
                payload JSON NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'pending\',
                attempts INT NOT NULL DEFAULT 0,
                occurred_at DATETIME(6) NOT NULL,
                available_at DATETIME(6) NOT NULL,
                UNIQUE KEY uq_outbox_event_id (event_id),
                KEY idx_outbox_pending (status, available_at)
            )',
        );

        $uuid = '0190a5e3-2b8c-7def-89ab-cdef01234567';
        $this->connection->executeStatement(
            "INSERT INTO outbox_messages (id, event_id, event_type, aggregate_type, aggregate_id, payload, occurred_at, available_at)
             VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
            [$uuid, $uuid, 'TestEvent', 'test', '0190a5e3-2b8c-7def-89ab-cdef01234568', '{"key":"value"}'],
        );

        $result = $this->connection->executeQuery(
            'SELECT status, attempts, event_type FROM outbox_messages WHERE id = ?',
            [$uuid],
        );
        $row = $result->fetchAssociative();

        $this->assertSame('pending', $row['status'], 'Status must be pending');
        $this->assertSame(0, (int) $row['attempts'], 'Attempts must be 0');
        $this->assertSame('TestEvent', $row['event_type'], 'Event type must match');
    }
}
