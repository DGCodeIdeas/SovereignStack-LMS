<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Domain\Repository;

use SovereignStack\Lms\Identity\Domain\ValueObject\PermissionCode;
use SovereignStack\Lms\Identity\Domain\ValueObject\RoleIdentifier;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdInterface;

interface RoleRepositoryInterface
{
    public function findIdByCode(RoleIdentifier $role): ?string;

    /**
     * @return list<RoleIdentifier>
     */
    public function rolesForUser(UserId $userId, ?OrganisationIdInterface $organisationId = null): array;

    /**
     * @return list<PermissionCode>
     */
    public function permissionsForUser(UserId $userId, ?OrganisationIdInterface $organisationId = null): array;

    public function assign(
        UserId $userId,
        RoleIdentifier $role,
        ?OrganisationIdInterface $organisationId,
        ?UserId $assignedBy,
        \DateTimeImmutable $assignedAt,
    ): void;

    /**
     * @return list<array{code: string, name: string, description: null|string}>
     */
    public function listAll(): array;

    /**
     * @return list<array{
     *     roleCode: string,
     *     roleName: string,
     *     organisationId: null|string,
     *     assignedAt: string
     * }>
     */
    public function assignmentsForUser(UserId $userId): array;

    public function revoke(
        UserId $userId,
        RoleIdentifier $role,
        ?OrganisationIdInterface $organisationId,
    ): void;

    public function countUsersWithRole(
        RoleIdentifier $role,
        ?OrganisationIdInterface $organisationId = null,
    ): int;
}
