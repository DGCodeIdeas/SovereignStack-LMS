<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

abstract class DatabaseIntegrationTestCase extends TestCase
{
    protected Connection $connection;

    protected function setUp(): void
    {
        $databaseUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? null;
        if ($databaseUrl === null || str_contains($databaseUrl, 'lms_test@127.0.0.1')) {
            $this->markTestSkipped(
                'Database integration tests require a real MySQL/MariaDB connection. '
                . 'Set DATABASE_URL in the test environment.',
            );
        }

        $this->connection = $this->createConnection($databaseUrl);
        $this->connection->executeStatement('SELECT 1');
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
        $params = $this->connection->getParams()['platform'] ?? null;
        $platform = $this->connection->getDatabasePlatform();
        $platformClass = $platform::class;

        return match (true) {
            str_contains($platformClass, 'MySQL84') || str_contains($platformClass, 'MySQL80') => 'mysql',
            str_contains($platformClass, 'MariaDB') => 'mariadb',
            default => 'unknown:' . $platformClass,
        };
    }

    private function createConnection(string $url): Connection
    {
        $config = new \Doctrine\DBAL\Configuration();
        return \Doctrine\DBAL\DriverManager::getConnection(['url' => $url], $config);
    }
}
