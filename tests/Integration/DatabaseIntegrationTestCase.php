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
        // PHPUnit's <env> in phpunit.xml.dist sets env vars, but they may be
        // overridden by CI. Try multiple sources.
        $databaseUrl = null;

        // Check $_ENV first (populated when variables_order includes E)
        if (isset($_ENV['DATABASE_URL']) && is_string($_ENV['DATABASE_URL']) && $_ENV['DATABASE_URL'] !== '') {
            $databaseUrl = $_ENV['DATABASE_URL'];
        }

        // Check $_SERVER
        if ($databaseUrl === null && isset($_SERVER['DATABASE_URL']) && is_string($_SERVER['DATABASE_URL']) && $_SERVER['DATABASE_URL'] !== '') {
            $databaseUrl = $_SERVER['DATABASE_URL'];
        }

        // Check getenv as last resort
        if ($databaseUrl === null) {
            $envVal = getenv('DATABASE_URL');
            if (is_string($envVal) && $envVal !== '') {
                $databaseUrl = $envVal;
            }
        }

        if ($databaseUrl === null) {
            $this->markTestSkipped('DATABASE_URL not set');
        }

        try {
            // Parse the URL manually to avoid DBAL 4.x URL parsing issues
            $parts = parse_url($databaseUrl);
            $this->connection = DriverManager::getConnection([
                'driver' => 'pdo_mysql',
                'host' => $parts['host'] ?? '127.0.0.1',
                'port' => $parts['port'] ?? 3306,
                'user' => $parts['user'] ?? 'lms_test',
                'password' => $parts['pass'] ?? 'lms_test',
                'dbname' => ltrim($parts['path'] ?? '/lms_test', '/'),
                'charset' => 'utf8mb4',
            ]);
            $this->connection->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database connection failed: ' . $e->getMessage());
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
