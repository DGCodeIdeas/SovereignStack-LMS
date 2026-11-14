<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

abstract class DatabaseIntegrationTestCase extends TestCase
{
    protected Connection $connection;

    protected function setUp(): void
    {
        $databaseUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? null;

        if ($databaseUrl === null) {
            $envVal = getenv('DATABASE_URL');
            if ($envVal !== false && $envVal !== '') {
                $databaseUrl = $envVal;
            }
        }

        if ($databaseUrl === null) {
            $this->markTestSkipped('DATABASE_URL not set; skipping database integration test.');
        }

        try {
            $this->connection = DriverManager::getConnection(['url' => (string) $databaseUrl]);
            $this->connection->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'Database connection failed: ' . $e->getMessage(),
            );
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            try {
                $this->connection->close();
            } catch (\Throwable) {
            }
        }
    }

    protected function getDatabaseEngine(): string
    {
        $platform = $this->connection->getDatabasePlatform();
        $platformClass = $platform::class;

        return match (true) {
            str_contains($platformClass, 'MySQL') => 'mysql',
            str_contains($platformClass, 'MariaDB') => 'mariadb',
            default => 'unknown:' . $platformClass,
        };
    }
}
