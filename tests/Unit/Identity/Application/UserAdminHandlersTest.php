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
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Tests\Support\Fakes\FakePermissionChecker;
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
            $this->audit,
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
            $this->audit,
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
            $this->audit,
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
            $this->audit,
            $this->clock,
        );

        ($handler)(new DeactivateUserCommand(self::ACTOR, self::TARGET));

        $user = $this->users->findById($this->targetId);
        self::assertNotNull($user);
        self::assertSame(UserStatus::DEACTIVATED, $user->status());
        self::assertTrue($this->audit->hasEvent('user.deactivated'));
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
