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
            fwrite(STDERR, "SKIP: DATABASE_URL not set. _ENV keys: " . implode(',', array_keys($_ENV)) . " _SERVER keys: " . implode(',', array_keys($_SERVER)) . " getenv: " . var_export(getenv('DATABASE_URL'), true) . "\n");
            $this->markTestSkipped('DATABASE_URL not set');
        }

        fwrite(STDERR, "Connecting to: " . $databaseUrl . "\n");
        try {
            $this->connection = DriverManager::getConnection(['url' => $databaseUrl, 'driver' => 'pdo_mysql']);
            $this->connection->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            fwrite(STDERR, "SKIP: Connection failed: " . $e->getMessage() . "\n");
            $this->markTestSkipped(
                'Database connection failed for URL ' . $databaseUrl . ': ' . $e->getMessage(),
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
