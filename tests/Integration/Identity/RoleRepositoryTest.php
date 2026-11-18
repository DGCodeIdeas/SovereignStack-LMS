<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Identity;

use SovereignStack\Lms\Identity\Domain\Exception\RoleNotFoundException;
use SovereignStack\Lms\Identity\Domain\ValueObject\PermissionCode;
use SovereignStack\Lms\Identity\Domain\ValueObject\RoleIdentifier;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Identity\Infrastructure\Persistence\DBALRoleRepository;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;
use SovereignStack\Lms\Shared\Infrastructure\Uuid\SymfonyUidUuidGenerator;
use SovereignStack\Lms\Tests\Integration\DatabaseIntegrationTestCase;
use SovereignStack\Lms\Tests\Integration\IdentityOrganisationFixtures;

/**
 * @internal
 *
 * @coversNothing
 */
final class RoleRepositoryTest extends DatabaseIntegrationTestCase
{
    use IdentityOrganisationFixtures;

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncate('user_tokens', 'organisation_memberships', 'user_roles', 'user_profiles', 'users', 'organisations');
        $this->now = new \DateTimeImmutable('2026-11-14T10:00:00+00:00');
    }

    public function testFindIdByCodeReturnsSeededRole(): void
    {
        $repository = $this->repository();

        self::assertSame(
            '01900000-0000-7000-8000-000000000004',
            $repository->findIdByCode(RoleIdentifier::fromString(RoleIdentifier::LMS_LEARNER)),
        );
    }

    public function testGlobalRolesAndPermissions(): void
    {
        $repository = $this->repository();
        $userId = $this->userId();

        $repository->assign($userId, RoleIdentifier::fromString(RoleIdentifier::LMS_LEARNER), null, null, $this->now);

        $roles = $repository->rolesForUser($userId);
        $permissions = $repository->permissionsForUser($userId);

        self::assertSame(['lms:learner'], array_map(static fn (RoleIdentifier $role): string => $role->value(), $roles));
        self::assertCount(7, $permissions);
        self::assertContains(PermissionCode::COURSE_VIEW, array_map(static fn (PermissionCode $code): string => $code->value(), $permissions));
        self::assertContains(PermissionCode::ENROLLMENT_CREATE, array_map(static fn (PermissionCode $code): string => $code->value(), $permissions));
        self::assertContains(PermissionCode::FILE_DOWNLOAD, array_map(static fn (PermissionCode $code): string => $code->value(), $permissions));
    }

    public function testOrganisationScopedRolesCombineWithGlobalRoles(): void
    {
        $repository = $this->repository();
        $userId = $this->userId();
        $organisationId = OrganisationId::fromString($this->insertOrganisation('role-org-'.bin2hex(random_bytes(4))));
        $organisation = $organisationId;

        $repository->assign($userId, RoleIdentifier::fromString(RoleIdentifier::LMS_LEARNER), null, null, $this->now);
        $repository->assign($userId, RoleIdentifier::fromString(RoleIdentifier::LMS_INSTRUCTOR), $organisation, null, $this->now);

        $globalRoles = array_map(static fn (RoleIdentifier $role): string => $role->value(), $repository->rolesForUser($userId));
        self::assertSame(['lms:learner'], $globalRoles);

        $scopedRoles = array_map(static fn (RoleIdentifier $role): string => $role->value(), $repository->rolesForUser($userId, $organisation));
        self::assertSame(['lms:instructor', 'lms:learner'], $scopedRoles);

        $permissions = array_map(static fn (PermissionCode $code): string => $code->value(), $repository->permissionsForUser($userId, $organisation));
        self::assertContains(PermissionCode::COURSE_CREATE, $permissions);
        self::assertContains(PermissionCode::ENROLLMENT_CREATE, $permissions);
        self::assertContains(PermissionCode::FILE_DOWNLOAD, $permissions);
        self::assertNotContains(PermissionCode::USER_MANAGE, $permissions);
    }

    public function testAssignIsIdempotent(): void
    {
        $repository = $this->repository();
        $userId = $this->userId();
        $role = RoleIdentifier::fromString(RoleIdentifier::LMS_LEARNER);

        $repository->assign($userId, $role, null, null, $this->now);
        $repository->assign($userId, $role, null, null, $this->now->modify('+1 minute'));

        self::assertSame(1, $this->fetchInt('SELECT COUNT(*) FROM user_roles WHERE user_id = :user_id', ['user_id' => $userId->value()]));
    }

    public function testAssignUnknownRoleThrows(): void
    {
        $this->expectException(RoleNotFoundException::class);

        $this->repository()->assign(
            $this->userId(),
            RoleIdentifier::fromString('lms:ghost'),
            null,
            null,
            $this->now,
        );
    }

    public function testListAllReturnsSeededCatalogue(): void
    {
        $roles = $this->repository()->listAll();

        self::assertCount(4, $roles);
        self::assertSame(
            ['lms:admin', 'lms:instructor', 'lms:learner', 'platform:admin'],
            array_map(static fn (array $role): string => $role['code'], $roles),
        );
        self::assertSame('Platform Administrator', $roles[3]['name']);
        self::assertNull($roles[3]['description']);
    }

    public function testAssignmentsForUserIncludesRoleNameAndOrganisation(): void
    {
        $repository = $this->repository();
        $userId = $this->userId();
        $organisation = OrganisationId::fromString($this->insertOrganisation('assignment-org-'.bin2hex(random_bytes(4))));

        $repository->assign($userId, RoleIdentifier::fromString(RoleIdentifier::LMS_INSTRUCTOR), $organisation, null, $this->now);
        $repository->assign($userId, RoleIdentifier::fromString(RoleIdentifier::LMS_LEARNER), null, null, $this->now);

        $assignments = $repository->assignmentsForUser($userId);

        self::assertCount(2, $assignments);
        self::assertSame('lms:instructor', $assignments[0]['roleCode']);
        self::assertSame('Instructor', $assignments[0]['roleName']);
        self::assertSame($organisation->value(), $assignments[0]['organisationId']);
        self::assertSame($this->now->format('Y-m-d\TH:i:s.uP'), $assignments[0]['assignedAt']);
        self::assertSame('lms:learner', $assignments[1]['roleCode']);
        self::assertNull($assignments[1]['organisationId']);
    }

    public function testRevokeDeletesOnlyMatchingAssignment(): void
    {
        $repository = $this->repository();
        $userId = $this->userId();
        $organisation = OrganisationId::fromString($this->insertOrganisation('revoke-org-'.bin2hex(random_bytes(4))));
        $role = RoleIdentifier::fromString(RoleIdentifier::LMS_INSTRUCTOR);

        $repository->assign($userId, $role, null, null, $this->now);
        $repository->assign($userId, $role, $organisation, null, $this->now);

        $repository->revoke($userId, $role, $organisation);

        self::assertSame(1, $this->fetchInt(
            'SELECT COUNT(*) FROM user_roles WHERE user_id = :user_id',
            ['user_id' => $userId->value()],
        ));
        self::assertSame(1, $this->fetchInt(
            'SELECT COUNT(*) FROM user_roles WHERE user_id = :user_id AND organisation_id IS NULL',
            ['user_id' => $userId->value()],
        ));

        $repository->revoke($userId, $role, null);

        self::assertSame(0, $this->fetchInt(
            'SELECT COUNT(*) FROM user_roles WHERE user_id = :user_id',
            ['user_id' => $userId->value()],
        ));
    }

    public function testCountUsersWithRoleCountsDistinctUsersAndScopes(): void
    {
        $repository = $this->repository();
        $first = $this->userId();
        $second = $this->userId();
        $organisation = OrganisationId::fromString($this->insertOrganisation('count-org-'.bin2hex(random_bytes(4))));
        $role = RoleIdentifier::fromString(RoleIdentifier::LMS_LEARNER);

        self::assertSame(0, $repository->countUsersWithRole($role));

        $repository->assign($first, $role, null, null, $this->now);
        $repository->assign($first, $role, null, null, $this->now->modify('+1 minute'));
        $repository->assign($second, $role, null, null, $this->now);
        $repository->assign($second, $role, $organisation, null, $this->now);

        self::assertSame(2, $repository->countUsersWithRole($role));
        self::assertSame(1, $repository->countUsersWithRole($role, $organisation));
        self::assertSame(0, $repository->countUsersWithRole(RoleIdentifier::fromString(RoleIdentifier::PLATFORM_ADMIN)));
    }

    private function repository(): DBALRoleRepository
    {
        return new DBALRoleRepository($this->connection(), new SymfonyUidUuidGenerator());
    }

    private function userId(): UserId
    {
        return UserId::fromString($this->insertUser('roles-'.bin2hex(random_bytes(4)).'@example.com'));
    }
}
