<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Process\Process;

abstract class DatabaseIntegrationTestCase extends KernelTestCase
{
    private static bool $schemaReady = false;

    public static function setUpBeforeClass(): void
    {
        if (self::$schemaReady) {
            return;
        }

        self::runMigrations();
        self::$schemaReady = true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->connection();
    }

    protected function connection(): Connection
    {
        $this->databaseUrl();

        try {
            $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        } catch (\Throwable $e) {
            self::fail('Database connection unavailable: '.$e->getMessage());
        }

        if (!$connection instanceof Connection) {
            self::fail('Doctrine DBAL connection service is missing or has the wrong type.');
        }

        try {
            $connection->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            self::fail('Database connection failed: '.$e->getMessage());
        }

        return $connection;
    }

    protected function databaseUrl(): string
    {
        $url = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
        if (!\is_string($url) || '' === $url) {
            self::fail('DATABASE_URL is required for integration tests. Set it to a real MySQL 8.4 or MariaDB 12.3 instance.');
        }

        return $url;
    }

    protected function secondConnection(): Connection
    {
        $parser = new DsnParser(['mysql' => 'pdo_mysql']);
        $params = $parser->parse($this->databaseUrl());

        return DriverManager::getConnection($params);
    }

    /**
     * @param array<int|string, mixed> $params
     */
    protected function fetchInt(string $sql, array $params = []): int
    {
        return self::asInt($this->connection()->fetchOne($sql, $params));
    }

    /**
     * @param array<int|string, mixed> $params
     */
    protected function fetchString(string $sql, array $params = []): string
    {
        return self::asString($this->connection()->fetchOne($sql, $params));
    }

    protected static function asInt(mixed $value): int
    {
        if (!\is_numeric($value)) {
            self::fail('Expected a numeric database value, got '.get_debug_type($value).'.');
        }

        return (int) $value;
    }

    protected static function asString(mixed $value): string
    {
        if (!\is_string($value)) {
            self::fail('Expected a string database value, got '.get_debug_type($value).'.');
        }

        return $value;
    }

    protected function truncate(string ...$tables): void
    {
        $connection = $this->connection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($tables as $table) {
                $connection->executeStatement('TRUNCATE TABLE '.$table);
            }
        } finally {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    protected function bus(): MessageBusInterface
    {
        $bus = self::getContainer()->get(MessageBusInterface::class);
        if (!$bus instanceof MessageBusInterface) {
            self::fail('Message bus service is missing.');
        }

        return $bus;
    }

    private static function runMigrations(): void
    {
        $root = \dirname(__DIR__, 2);
        $env = array_filter($_SERVER, 'is_string') + array_filter($_ENV, 'is_string');
        $env['APP_ENV'] = 'test';
        $env['APP_DEBUG'] = '1';

        $process = new Process(
            [\PHP_BINARY, $root.'/bin/console', 'doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration'],
            $root,
            $env,
        );
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            self::fail("Migrations failed:\n".$process->getErrorOutput()."\n".$process->getOutput());
        }
    }
}
