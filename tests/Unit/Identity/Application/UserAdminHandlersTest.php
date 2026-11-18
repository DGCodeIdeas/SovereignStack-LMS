<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Identity\Application;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Identity\Application\Command\AssignRoleCommand;
use SovereignStack\Lms\Identity\Application\Command\DeactivateUserCommand;
use SovereignStack\Lms\Identity\Application\Command\SuspendUserCommand;
use SovereignStack\Lms\Identity\Application\Handler\AssignRoleHandler;
use SovereignStack\Lms\Identity\Application\Handler\DeactivateUserHandler;
use SovereignStack\Lms\Identity\Application\Handler\SuspendUserHandler;
use SovereignStack\Lms\Identity\Domain\Entity\User;
use SovereignStack\Lms\Identity\Domain\Enum\UserStatus;
use SovereignStack\Lms\Identity\Domain\Exception\LastPlatformAdministratorException;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Identity\Domain\ValueObject\RoleIdentifier;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Tests\Support\Fakes\FakePermissionChecker;
use SovereignStack\Lms\Tests\Support\Fakes\FakeTransactionManager;
use SovereignStack\Lms\Tests\Support\Fakes\FixedClock;
use SovereignStack\Lms\Tests\Support\Fakes\InMemoryRoleRepository;
use SovereignStack\Lms\Tests\Support\Fakes\InMemoryUserRepository;
use SovereignStack\Lms\Tests\Support\Fakes\RecordingAuditLogger;

/**
 * @internal
 *
 * @coversNothing
 */
final class UserAdminHandlersTest extends TestCase
{
    private const ACTOR = '01900000-0000-7000-8000-000000000900';
    private const TARGET = '01900000-0000-7000-8000-000000000001';

    private FixedClock $clock;
    private InMemoryUserRepository $users;
    private RecordingAuditLogger $audit;
    private UserId $targetId;

    protected function setUp(): void
    {
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-11-14T10:00:00+00:00'));
        $this->users = new InMemoryUserRepository();
        $this->audit = new RecordingAuditLogger();
        $this->targetId = UserId::fromString(self::TARGET);

        $user = User::register(
            $this->targetId,
            Email::fromString('jane@example.com'),
            'hashed:correct-horse-battery',
            $this->clock->now(),
        );
        $user->verifyEmail($this->clock->now());
        $this->users->save($user);
    }

    public function testSuspendRequiresPermission(): void
    {
        $handler = new SuspendUserHandler(
            new FakePermissionChecker(['user.manage']),
            $this->users,
            new InMemoryRoleRepository(),
            $this->audit,
            new FakeTransactionManager(),
            $this->clock,
        );

        $this->expectException(ForbiddenException::class);

        ($handler)(new SuspendUserCommand(self::ACTOR, self::TARGET));
    }

    public function testSuspendSucceedsAndAudits(): void
    {
        $handler = new SuspendUserHandler(
            new FakePermissionChecker(),
            $this->users,
            new InMemoryRoleRepository(),
            $this->audit,
            new FakeTransactionManager(),
            $this->clock,
        );

        ($handler)(new SuspendUserCommand(self::ACTOR, self::TARGET));

        $user = $this->users->findById($this->targetId);
        self::assertNotNull($user);
        self::assertSame(UserStatus::SUSPENDED, $user->status());
        self::assertTrue($this->audit->hasEvent('user.suspended'));
    }

    public function testDeactivateRequiresPermission(): void
    {
        $handler = new DeactivateUserHandler(
            new FakePermissionChecker(['user.manage']),
            $this->users,
            new InMemoryRoleRepository(),
            $this->audit,
            new FakeTransactionManager(),
            $this->clock,
        );

        $this->expectException(ForbiddenException::class);

        ($handler)(new DeactivateUserCommand(self::ACTOR, self::TARGET));
    }

    public function testDeactivateSucceedsAndAudits(): void
    {
        $handler = new DeactivateUserHandler(
            new FakePermissionChecker(),
            $this->users,
            new InMemoryRoleRepository(),
            $this->audit,
            new FakeTransactionManager(),
            $this->clock,
        );

        ($handler)(new DeactivateUserCommand(self::ACTOR, self::TARGET));

        $user = $this->users->findById($this->targetId);
        self::assertNotNull($user);
        self::assertSame(UserStatus::DEACTIVATED, $user->status());
        self::assertTrue($this->audit->hasEvent('user.deactivated'));
    }

    public function testSuspendingLastActivePlatformAdminIsRejected(): void
    {
        $roles = new InMemoryRoleRepository();
        $roles->assign($this->targetId, RoleIdentifier::fromString(RoleIdentifier::PLATFORM_ADMIN), null, null, $this->clock->now());

        $handler = new SuspendUserHandler(
            new FakePermissionChecker(),
            $this->users,
            $roles,
            $this->audit,
            new FakeTransactionManager(),
            $this->clock,
        );

        $this->expectException(LastPlatformAdministratorException::class);

        ($handler)(new SuspendUserCommand(self::ACTOR, self::TARGET));
    }

    public function testSuspendingNonLastPlatformAdminSucceeds(): void
    {
        $roles = new InMemoryRoleRepository();
        $roles->assign($this->targetId, RoleIdentifier::fromString(RoleIdentifier::PLATFORM_ADMIN), null, null, $this->clock->now());
        $otherId = UserId::fromString('01900000-0000-7000-8000-000000000902');
        $other = User::register($otherId, Email::fromString('other@example.com'), 'hash', $this->clock->now());
        $other->verifyEmail($this->clock->now());
        $this->users->save($other);
        $roles->assign($otherId, RoleIdentifier::fromString(RoleIdentifier::PLATFORM_ADMIN), null, null, $this->clock->now());

        $transactions = new FakeTransactionManager();
        $handler = new SuspendUserHandler(
            new FakePermissionChecker(),
            $this->users,
            $roles,
            $this->audit,
            $transactions,
            $this->clock,
        );

        ($handler)(new SuspendUserCommand(self::ACTOR, self::TARGET));

        $user = $this->users->findById($this->targetId);
        self::assertNotNull($user);
        self::assertSame(UserStatus::SUSPENDED, $user->status());
        self::assertTrue($this->audit->hasEvent('user.suspended'));
        self::assertSame(1, $transactions->calls);
    }

    public function testSuspendingLastActivePlatformAdminKeepsAccountActiveAndUnaudited(): void
    {
        $roles = new InMemoryRoleRepository();
        $roles->assign($this->targetId, RoleIdentifier::fromString(RoleIdentifier::PLATFORM_ADMIN), null, null, $this->clock->now());

        $transactions = new FakeTransactionManager();
        $handler = new SuspendUserHandler(
            new FakePermissionChecker(),
            $this->users,
            $roles,
            $this->audit,
            $transactions,
            $this->clock,
        );

        try {
            ($handler)(new SuspendUserCommand(self::ACTOR, self::TARGET));
            self::fail('Expected LastPlatformAdministratorException.');
        } catch (LastPlatformAdministratorException) {
        }

        $user = $this->users->findById($this->targetId);
        self::assertNotNull($user);
        self::assertSame(UserStatus::ACTIVE, $user->status());
        self::assertSame([], $this->audit->records);
        self::assertSame(1, $transactions->calls);
    }

    public function testSuspendingInactiveAccountIsNoOp(): void
    {
        $user = $this->users->findById($this->targetId);
        self::assertNotNull($user);
        $user->suspend($this->clock->now());
        $this->users->save($user);

        $transactions = new FakeTransactionManager();
        $handler = new SuspendUserHandler(
            new FakePermissionChecker(),
            $this->users,
            new InMemoryRoleRepository(),
            $this->audit,
            $transactions,
            $this->clock,
        );

        ($handler)(new SuspendUserCommand(self::ACTOR, self::TARGET));

        self::assertSame([], $this->audit->records);
        self::assertSame(UserStatus::SUSPENDED, $this->users->findById($this->targetId)?->status());
        self::assertSame(1, $transactions->calls);
    }

    public function testDeactivatingLastActivePlatformAdminIsRejected(): void
    {
        $roles = new InMemoryRoleRepository();
        $roles->assign($this->targetId, RoleIdentifier::fromString(RoleIdentifier::PLATFORM_ADMIN), null, null, $this->clock->now());

        $handler = new DeactivateUserHandler(
            new FakePermissionChecker(),
            $this->users,
            $roles,
            $this->audit,
            new FakeTransactionManager(),
            $this->clock,
        );

        $this->expectException(LastPlatformAdministratorException::class);

        ($handler)(new DeactivateUserCommand(self::ACTOR, self::TARGET));
    }

    public function testDeactivatingLastActivePlatformAdminKeepsAccountActiveAndUnaudited(): void
    {
        $roles = new InMemoryRoleRepository();
        $roles->assign($this->targetId, RoleIdentifier::fromString(RoleIdentifier::PLATFORM_ADMIN), null, null, $this->clock->now());

        $transactions = new FakeTransactionManager();
        $handler = new DeactivateUserHandler(
            new FakePermissionChecker(),
            $this->users,
            $roles,
            $this->audit,
            $transactions,
            $this->clock,
        );

        try {
            ($handler)(new DeactivateUserCommand(self::ACTOR, self::TARGET));
            self::fail('Expected LastPlatformAdministratorException.');
        } catch (LastPlatformAdministratorException) {
        }

        $user = $this->users->findById($this->targetId);
        self::assertNotNull($user);
        self::assertSame(UserStatus::ACTIVE, $user->status());
        self::assertSame([], $this->audit->records);
        self::assertSame(1, $transactions->calls);
    }

    public function testDeactivatingNonLastPlatformAdminSucceeds(): void
    {
        $roles = new InMemoryRoleRepository();
        $roles->assign($this->targetId, RoleIdentifier::fromString(RoleIdentifier::PLATFORM_ADMIN), null, null, $this->clock->now());
        $otherId = UserId::fromString('01900000-0000-7000-8000-000000000902');
        $other = User::register($otherId, Email::fromString('other@example.com'), 'hash', $this->clock->now());
        $other->verifyEmail($this->clock->now());
        $this->users->save($other);
        $roles->assign($otherId, RoleIdentifier::fromString(RoleIdentifier::PLATFORM_ADMIN), null, null, $this->clock->now());

        $transactions = new FakeTransactionManager();
        $handler = new DeactivateUserHandler(
            new FakePermissionChecker(),
            $this->users,
            $roles,
            $this->audit,
            $transactions,
            $this->clock,
        );

        ($handler)(new DeactivateUserCommand(self::ACTOR, self::TARGET));

        $user = $this->users->findById($this->targetId);
        self::assertNotNull($user);
        self::assertSame(UserStatus::DEACTIVATED, $user->status());
        self::assertTrue($this->audit->hasEvent('user.deactivated'));
        self::assertSame(1, $transactions->calls);
    }

    public function testAssignRoleRequiresPermission(): void
    {
        $roles = new InMemoryRoleRepository();
        $handler = new AssignRoleHandler(
            new FakePermissionChecker(['user.roles.assign']),
            $this->users,
            $roles,
            $this->audit,
            $this->clock,
        );

        $this->expectException(ForbiddenException::class);

        ($handler)(new AssignRoleCommand(self::ACTOR, self::TARGET, 'lms:learner', null));
    }

    public function testAssignRoleIsIdempotentAndAuditsOnce(): void
    {
        $roles = new InMemoryRoleRepository();
        $handler = new AssignRoleHandler(new FakePermissionChecker(), $this->users, $roles, $this->audit, $this->clock);
        $command = new AssignRoleCommand(self::ACTOR, self::TARGET, 'lms:learner', null);

        ($handler)($command);
        ($handler)($command);

        self::assertCount(1, $roles->assignments);
        self::assertSame('lms:learner', $roles->assignments[0]['role']);
        self::assertNull($roles->assignments[0]['organisationId']);
        self::assertTrue($this->audit->hasEvent('user.role_assigned'));
    }
}
