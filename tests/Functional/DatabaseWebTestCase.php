<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Process\Process;

abstract class DatabaseWebTestCase extends WebTestCase
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

    protected function tearDown(): void
    {
        parent::tearDown();
        static::ensureKernelShutdown();
    }

    protected function client(): KernelBrowser
    {
        return static::createClient();
    }

    protected function connection(): Connection
    {
        $this->databaseUrl();

        if (!static::$booted) {
            static::bootKernel();
        }

        try {
            $connection = static::getContainer()->get('doctrine.dbal.default_connection');
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
            self::fail('DATABASE_URL is required for functional tests. Set it to a real MySQL 8.4 or MariaDB 12.3 instance.');
        }

        return $url;
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
