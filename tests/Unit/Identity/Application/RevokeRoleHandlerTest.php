<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Identity\Application;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Identity\Application\Command\RevokeRoleCommand;
use SovereignStack\Lms\Identity\Application\Handler\RevokeRoleHandler;
use SovereignStack\Lms\Identity\Domain\Entity\User;
use SovereignStack\Lms\Identity\Domain\Exception\LastPlatformAdministratorException;
use SovereignStack\Lms\Identity\Domain\Exception\UserNotFoundException;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Identity\Domain\ValueObject\RoleIdentifier;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;
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
final class RevokeRoleHandlerTest extends TestCase
{
    private const ACTOR = '01900000-0000-7000-8000-000000000900';
    private const TARGET = '01900000-0000-7000-8000-000000000001';
    private const ORGANISATION = '01900000-0000-7000-8000-000000000100';

    private FixedClock $clock;
    private InMemoryUserRepository $users;
    private InMemoryRoleRepository $roles;
    private RecordingAuditLogger $audit;
    private UserId $targetId;

    protected function setUp(): void
    {
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-11-17T10:00:00+00:00'));
        $this->users = new InMemoryUserRepository();
        $this->roles = new InMemoryRoleRepository();
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

    public function testRevokesGlobalRoleAndAudits(): void
    {
        $this->roles->assign(
            $this->targetId,
            RoleIdentifier::fromString(RoleIdentifier::LMS_LEARNER),
            null,
            null,
            $this->clock->now(),
        );

        ($this->handler())(new RevokeRoleCommand(self::ACTOR, self::TARGET, RoleIdentifier::LMS_LEARNER, null));

        self::assertCount(0, $this->roles->assignments);
        self::assertTrue($this->audit->hasEvent('user.role_revoked'));
        self::assertSame(['role' => 'lms:learner', 'organisation_id' => null], $this->audit->records[0]['data']);
    }

    public function testRevokesOrganisationScopedRoleOnly(): void
    {
        $organisation = OrganisationIdReference::fromString(self::ORGANISATION);
        $role = RoleIdentifier::fromString(RoleIdentifier::LMS_INSTRUCTOR);

        $this->roles->assign($this->targetId, $role, null, null, $this->clock->now());
        $this->roles->assign($this->targetId, $role, $organisation, null, $this->clock->now());

        ($this->handler())(new RevokeRoleCommand(self::ACTOR, self::TARGET, RoleIdentifier::LMS_INSTRUCTOR, self::ORGANISATION));

        self::assertCount(1, $this->roles->assignments);
        self::assertNull($this->roles->assignments[0]['organisationId']);
    }

    public function testRevokeIsIdempotentWhenAssignmentMissing(): void
    {
        $handler = $this->handler();
        $command = new RevokeRoleCommand(self::ACTOR, self::TARGET, RoleIdentifier::LMS_LEARNER, null);

        ($handler)($command);
        ($handler)($command);

        self::assertCount(0, $this->roles->assignments);
        self::assertSame([], $this->audit->records);
    }

    public function testRevokingLastPlatformAdministratorIsBlocked(): void
    {
        $this->roles->assign(
            $this->targetId,
            RoleIdentifier::fromString(RoleIdentifier::PLATFORM_ADMIN),
            null,
            null,
            $this->clock->now(),
        );

        $this->expectException(LastPlatformAdministratorException::class);

        ($this->handler())(new RevokeRoleCommand(self::ACTOR, self::TARGET, RoleIdentifier::PLATFORM_ADMIN, null));
    }

    public function testPlatformAdministratorRemainsWhenRevokeIsBlocked(): void
    {
        $this->roles->assign(
            $this->targetId,
            RoleIdentifier::fromString(RoleIdentifier::PLATFORM_ADMIN),
            null,
            null,
            $this->clock->now(),
        );

        try {
            ($this->handler())(new RevokeRoleCommand(self::ACTOR, self::TARGET, RoleIdentifier::PLATFORM_ADMIN, null));
        } catch (LastPlatformAdministratorException) {
        }

        self::assertCount(1, $this->roles->assignments);
    }

    public function testRevokeRequiresAssignPermission(): void
    {
        $handler = new RevokeRoleHandler(
            new FakePermissionChecker(['user.roles.assign']),
            $this->users,
            $this->roles,
            $this->audit,
        );

        $this->expectException(ForbiddenException::class);

        ($handler)(new RevokeRoleCommand(self::ACTOR, self::TARGET, RoleIdentifier::LMS_LEARNER, null));
    }

    public function testInvalidRoleCodeIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        ($this->handler())(new RevokeRoleCommand(self::ACTOR, self::TARGET, 'not a role', null));
    }

    public function testInvalidOrganisationIdIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        ($this->handler())(new RevokeRoleCommand(self::ACTOR, self::TARGET, RoleIdentifier::LMS_LEARNER, '  '));
    }

    public function testUnknownTargetIsRejected(): void
    {
        $this->expectException(UserNotFoundException::class);

        ($this->handler())(new RevokeRoleCommand(
            self::ACTOR,
            '01900000-0000-7000-8000-0000000000ff',
            RoleIdentifier::LMS_LEARNER,
            null,
        ));
    }

    private function handler(): RevokeRoleHandler
    {
        return new RevokeRoleHandler(new FakePermissionChecker(), $this->users, $this->roles, $this->audit);
    }
}
