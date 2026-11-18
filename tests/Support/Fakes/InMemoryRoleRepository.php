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
    private const ROLE_NAMES = [
        RoleIdentifier::PLATFORM_ADMIN => 'Platform Administrator',
        RoleIdentifier::LMS_ADMIN => 'LMS Administrator',
        RoleIdentifier::LMS_INSTRUCTOR => 'Instructor',
        RoleIdentifier::LMS_LEARNER => 'Learner',
    ];

    /**
     * @var list<array{userId: string, role: string, organisationId: null|string}>
     */
    public array $assignments = [];

    /**
     * @var array<string, \DateTimeImmutable>
     */
    private array $assignmentTimes = [];

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

    public function lockRole(RoleIdentifier $role): ?string
    {
        return $this->findIdByCode($role);
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

        $this->assignmentTimes[$this->key($userId->value(), $role->value(), $orgValue)] = $assignedAt;
        $this->roles[$userId->value()][] = $role;
    }

    public function listAll(): array
    {
        $roles = [];

        foreach (self::ROLE_NAMES as $code => $name) {
            $roles[] = [
                'code' => $code,
                'name' => $name,
                'description' => null,
            ];
        }

        return $roles;
    }

    public function assignmentsForUser(UserId $userId): array
    {
        $result = [];

        foreach ($this->assignments as $assignment) {
            if ($assignment['userId'] !== $userId->value()) {
                continue;
            }

            $result[] = [
                'roleCode' => $assignment['role'],
                'roleName' => self::ROLE_NAMES[$assignment['role']] ?? $assignment['role'],
                'organisationId' => $assignment['organisationId'],
                'assignedAt' => ($this->assignmentTimes[$this->key(
                    $assignment['userId'],
                    $assignment['role'],
                    $assignment['organisationId'],
                )] ?? new \DateTimeImmutable('@0'))->format(\DATE_ATOM),
            ];
        }

        return $result;
    }

    public function revoke(
        UserId $userId,
        RoleIdentifier $role,
        ?OrganisationIdInterface $organisationId,
    ): void {
        $orgValue = $organisationId?->value();

        $this->assignments = array_values(array_filter(
            $this->assignments,
            static fn (array $assignment): bool => !(
                $assignment['userId'] === $userId->value()
                && $assignment['role'] === $role->value()
                && $assignment['organisationId'] === $orgValue
            ),
        ));

        unset($this->assignmentTimes[$this->key($userId->value(), $role->value(), $orgValue)]);
        $this->rebuildRoles();
    }

    public function countUsersWithRole(
        RoleIdentifier $role,
        ?OrganisationIdInterface $organisationId = null,
    ): int {
        $orgValue = $organisationId?->value();
        $userIds = [];

        foreach ($this->assignments as $assignment) {
            if ($assignment['role'] !== $role->value() || $assignment['organisationId'] !== $orgValue) {
                continue;
            }

            $userIds[$assignment['userId']] = true;
        }

        return \count($userIds);
    }

    private function key(string $userId, string $role, ?string $organisationId): string
    {
        return $userId.'|'.$role.'|'.($organisationId ?? '');
    }

    private function rebuildRoles(): void
    {
        $this->roles = [];

        foreach ($this->assignments as $assignment) {
            $userId = $assignment['userId'];
            $role = RoleIdentifier::fromString($assignment['role']);

            if (!isset($this->roles[$userId])) {
                $this->roles[$userId] = [];
            }

            foreach ($this->roles[$userId] as $existing) {
                if ($existing->equals($role)) {
                    continue 2;
                }
            }

            $this->roles[$userId][] = $role;
        }
    }
}
