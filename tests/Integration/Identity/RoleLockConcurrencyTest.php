<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Identity;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Tools\DsnParser;
use SovereignStack\Lms\Tests\Integration\DatabaseIntegrationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class RoleLockConcurrencyTest extends DatabaseIntegrationTestCase
{
    public function testPlatformAdminRoleRowLockSerializesConcurrentChecks(): void
    {
        $first = $this->connection();
        $url = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');

        self::assertIsString($url);
        self::assertNotSame('', $url);

        $second = DriverManager::getConnection((new DsnParser(['mysql' => 'pdo_mysql']))->parse($url));
        $second->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');

        $first->beginTransaction();

        try {
            $first->fetchOne(
                'SELECT id FROM roles WHERE code = :code FOR UPDATE',
                ['code' => 'platform:admin'],
            );

            $second->beginTransaction();
            $thrown = null;

            try {
                $second->fetchOne(
                    'SELECT id FROM roles WHERE code = :code FOR UPDATE',
                    ['code' => 'platform:admin'],
                );
            } catch (\Throwable $exception) {
                $thrown = $exception;
            }

            self::assertInstanceOf(Exception::class, $thrown);
        } finally {
            if ($second->isTransactionActive()) {
                $second->rollBack();
            }

            if ($first->isTransactionActive()) {
                $first->rollBack();
            }
        }
    }
}
