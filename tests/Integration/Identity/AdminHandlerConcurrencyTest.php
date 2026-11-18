<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Identity;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use SovereignStack\Lms\Identity\Application\Command\RevokeRoleCommand;
use SovereignStack\Lms\Identity\Application\Command\SuspendUserCommand;
use SovereignStack\Lms\Identity\Application\Handler\RevokeRoleHandler;
use SovereignStack\Lms\Identity\Application\Handler\SuspendUserHandler;
use SovereignStack\Lms\Identity\Domain\Entity\User;
use SovereignStack\Lms\Identity\Domain\Exception\LastPlatformAdministratorException;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Identity\Domain\ValueObject\RoleIdentifier;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Identity\Infrastructure\Persistence\DBALRoleRepository;
use SovereignStack\Lms\Identity\Infrastructure\Persistence\DBALUserRepository;
use SovereignStack\Lms\Shared\Infrastructure\Clock\SystemClock;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DbalTransactionManager;
use SovereignStack\Lms\Shared\Infrastructure\Uuid\SymfonyUidUuidGenerator;
use SovereignStack\Lms\Tests\Integration\DatabaseIntegrationTestCase;
use SovereignStack\Lms\Tests\Support\Fakes\FakePermissionChecker;
use SovereignStack\Lms\Tests\Support\Fakes\RecordingAuditLogger;

/**
 * @internal
 *
 * @coversNothing
 */
final class AdminHandlerConcurrencyTest extends DatabaseIntegrationTestCase
{
    private const ADMIN_A = '01900000-0000-7000-8000-00000000a001';
    private const ADMIN_B = '01900000-0000-7000-8000-00000000a002';

    public function testConcurrentRevokeAndSuspendCannotRemoveEveryActivePlatformAdmin(): void
    {
        if (!\function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the handler-level concurrency test.');
        }

        $this->truncate('user_roles', 'users');

        // Close the shared connections before forking: a forked child that
        // inherits one would send COM_QUIT on exit and kill the parent session.
        $this->connection()->close();

        $setup = $this->newConnection();
        $this->seedPlatformAdmins($setup);
        $setup->close();

        $pid = pcntl_fork();
        self::assertNotSame(-1, $pid);

        if (0 === $pid) {
            try {
                $connection = $this->newConnection();
                $this->suspendHandler($connection)(new SuspendUserCommand(self::ADMIN_B, self::ADMIN_A));
            } catch (LastPlatformAdministratorException) {
                // Expected when the competing revocation wins the role lock.
            } catch (\Throwable $exception) {
                fwrite(\STDERR, $exception->getMessage().PHP_EOL);

                exit(1);
            }

            exit(0);
        }

        $parent = $this->newConnection();

        try {
            $this->revokeHandler($parent)(new RevokeRoleCommand(
                self::ADMIN_A,
                self::ADMIN_B,
                RoleIdentifier::PLATFORM_ADMIN,
                null,
            ));
        } catch (LastPlatformAdministratorException) {
            // Expected when the competing suspension wins the role lock.
        }

        pcntl_waitpid($pid, $status);
        self::assertSame(0, pcntl_wexitstatus($status));

        $activeAdmins = $parent->fetchOne(
            'SELECT COUNT(DISTINCT ur.user_id) FROM user_roles ur'
            .' INNER JOIN roles r ON r.id = ur.role_id'
            .' INNER JOIN users u ON u.id = ur.user_id'
            ." WHERE r.code = 'platform:admin' AND u.status = 'active'"
        );

        $parent->close();

        self::assertSame(1, self::asInt($activeAdmins));
    }

    private function seedPlatformAdmins(Connection $connection): void
    {
        $users = new DBALUserRepository($connection);
        $roles = new DBALRoleRepository($connection, new SymfonyUidUuidGenerator());
        $now = new \DateTimeImmutable('2026-11-18T00:00:00+00:00');

        foreach ([self::ADMIN_A, self::ADMIN_B] as $id) {
            $userId = UserId::fromString($id);
            $user = User::register(
                $userId,
                Email::fromString($id.'@example.com'),
                'hash',
                $now,
            );
            $user->verifyEmail($now);
            $users->save($user);
            $roles->assign($userId, RoleIdentifier::fromString(RoleIdentifier::PLATFORM_ADMIN), null, null, $now);
        }
    }

    private function newConnection(): Connection
    {
        $url = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
        self::assertIsString($url);
        self::assertNotSame('', $url);

        return DriverManager::getConnection((new DsnParser(['mysql' => 'pdo_mysql']))->parse($url));
    }

    private function suspendHandler(Connection $connection): SuspendUserHandler
    {
        return new SuspendUserHandler(
            new FakePermissionChecker([]),
            new DBALUserRepository($connection),
            new DBALRoleRepository($connection, new SymfonyUidUuidGenerator()),
            new RecordingAuditLogger(),
            new DbalTransactionManager($connection),
            new SystemClock(),
        );
    }

    private function revokeHandler(Connection $connection): RevokeRoleHandler
    {
        return new RevokeRoleHandler(
            new FakePermissionChecker([]),
            new DBALUserRepository($connection),
            new DBALRoleRepository($connection, new SymfonyUidUuidGenerator()),
            new RecordingAuditLogger(),
            new DbalTransactionManager($connection),
        );
    }
}
