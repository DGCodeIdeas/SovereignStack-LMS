<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Support\Fakes;

use SovereignStack\Lms\Identity\Domain\Repository\RoleRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\PermissionCode;
use SovereignStack\Lms\Identity\Domain\ValueObject\RoleIdentifier;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdInterface;

final class InMemoryRoleRepository implements RoleRepositoryInterface
{
    /**
     * @var list<array{userId: string, role: string, organisationId: null|string}>
     */
    public array $assignments = [];

    /**
     * @var array<string, array<string, list<PermissionCode>>>
     */
    private array $permissions = [];

    /**
     * @var array<string, list<RoleIdentifier>>
     */
    private array $roles = [];

    public function grantGlobal(string $userId, string $permission): void
    {
        $this->permissions[$userId][''][] = PermissionCode::fromString($permission);
    }

    public function grantScoped(string $userId, string $organisationId, string $permission): void
    {
        $this->permissions[$userId][$organisationId][] = PermissionCode::fromString($permission);
    }

    public function findIdByCode(RoleIdentifier $role): ?string
    {
        return 'role-'.$role->value();
    }

    public function rolesForUser(UserId $userId, ?OrganisationIdInterface $organisationId = null): array
    {
        return $this->roles[$userId->value()] ?? [];
    }

    public function permissionsForUser(UserId $userId, ?OrganisationIdInterface $organisationId = null): array
    {
        $all = $this->permissions[$userId->value()] ?? [];
        $granted = $all[''] ?? [];

        if (null !== $organisationId) {
            foreach ($all[$organisationId->value()] ?? [] as $permission) {
                $granted[] = $permission;
            }
        }

        return $granted;
    }

    public function assign(
        UserId $userId,
        RoleIdentifier $role,
        ?OrganisationIdInterface $organisationId,
        ?UserId $assignedBy,
        \DateTimeImmutable $assignedAt,
    ): void {
        $orgValue = $organisationId?->value();

        foreach ($this->assignments as $assignment) {
            if ($assignment['userId'] === $userId->value()
                && $assignment['role'] === $role->value()
                && $assignment['organisationId'] === $orgValue) {
                return;
            }
        }

        $this->assignments[] = [
            'userId' => $userId->value(),
            'role' => $role->value(),
            'organisationId' => $orgValue,
        ];

        $this->roles[$userId->value()][] = $role;
    }
}
