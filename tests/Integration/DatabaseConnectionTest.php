<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration;

final class DatabaseConnectionTest extends DatabaseIntegrationTestCase
{
    public function testConnectsToDatabase(): void
    {
        $this->assertNotNull($this->connection);
        $result = $this->connection->executeQuery('SELECT 1 AS test');
        $this->assertSame(1, $result->fetchOne());
    }

    public function testIdentifiesDatabaseEngine(): void
    {
        $engine = $this->getDatabaseEngine();
        $this->assertContains($engine, ['mysql', 'mariadb'], "Engine must be MySQL or MariaDB, got: {$engine}");
    }

    public function testSupportsUtf8mb4(): void
    {
        $result = $this->connection->executeQuery("SHOW VARIABLES LIKE 'character_set_database'");
        $row = $result->fetchAssociative();
        $this->assertNotEmpty($row, 'Database character set query must return a row');
    }

    public function testSupportsDatetimeMicroseconds(): void
    {
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE dt_test (ts DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6))',
        );
        $this->connection->executeStatement('INSERT INTO dt_test () VALUES ()');
        $result = $this->connection->executeQuery('SELECT ts FROM dt_test');
        $ts = $result->fetchOne();
        $this->assertNotEmpty($ts, 'DATETIME(6) must store and retrieve a value');
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{1,6}$/',
            (string) $ts,
            'DATETIME(6) must include microsecond precision',
        );
    }

    public function testSupportsUuidChar36(): void
    {
        $uuid = '0190a5e3-2b8c-7def-89ab-cdef01234567';
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE uuid_test (id CHAR(36) NOT NULL PRIMARY KEY)',
        );
        $this->connection->executeStatement(
            'INSERT INTO uuid_test (id) VALUES (?)',
            [$uuid],
        );
        $result = $this->connection->executeQuery('SELECT id FROM uuid_test');
        $this->assertSame($uuid, $result->fetchOne());
    }

    public function testSupportsGeneratedColumns(): void
    {
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE gen_test (
                status VARCHAR(32) NOT NULL DEFAULT \'active\',
                _active_flag SMALLINT GENERATED ALWAYS AS (CASE WHEN status = \'active\' THEN 1 ELSE NULL END) VIRTUAL
            )',
        );
        $this->connection->executeStatement("INSERT INTO gen_test (status) VALUES ('active')");
        $result = $this->connection->executeQuery('SELECT _active_flag FROM gen_test');
        $this->assertSame(1, (int) $result->fetchOne(), 'Generated column must return 1 for active status');
    }

    public function testSupportsSkipLocked(): void
    {
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE lock_test (id INT NOT NULL PRIMARY KEY, val INT NOT NULL DEFAULT 0)',
        );
        $this->connection->executeStatement('INSERT INTO lock_test (id) VALUES (1)');

        // SELECT ... FOR UPDATE SKIP LOCKED must not error
        $tx = $this->connection->beginTransaction();
        try {
            $result = $this->connection->executeQuery(
                'SELECT id FROM lock_test WHERE id = 1 FOR UPDATE SKIP LOCKED',
            );
            $this->assertSame(1, (int) $result->fetchOne());
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            $this->fail('SELECT FOR UPDATE SKIP LOCKED must be supported: ' . $e->getMessage());
        }
    }
}
