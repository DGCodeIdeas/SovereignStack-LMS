<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use SovereignStack\Lms\Identity\Domain\Exception\RoleNotFoundException;
use SovereignStack\Lms\Identity\Domain\Repository\RoleRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\PermissionCode;
use SovereignStack\Lms\Identity\Domain\ValueObject\RoleIdentifier;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdInterface;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DateTimeCodec;

final readonly class DBALRoleRepository implements RoleRepositoryInterface
{
    public function __construct(
        private Connection $connection,
        private UuidGeneratorInterface $uuid,
    ) {}

    public function findIdByCode(RoleIdentifier $role): ?string
    {
        $id = $this->connection->fetchOne(
            'SELECT id FROM roles WHERE code = :code',
            ['code' => $role->value()],
        );

        return \is_string($id) ? $id : null;
    }

    public function rolesForUser(UserId $userId, ?OrganisationIdInterface $organisationId = null): array
    {
        $sql = 'SELECT DISTINCT r.code FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :user_id';
        $params = ['user_id' => $userId->value()];

        if (null === $organisationId) {
            $sql .= ' AND ur.organisation_id IS NULL';
        } else {
            $sql .= ' AND (ur.organisation_id IS NULL OR ur.organisation_id = :organisation_id)';
            $params['organisation_id'] = $organisationId->value();
        }

        $sql .= ' ORDER BY r.code';

        /** @var list<string> $codes */
        $codes = $this->connection->fetchFirstColumn($sql, $params);

        return array_map(static fn (string $code): RoleIdentifier => RoleIdentifier::fromString($code), $codes);
    }

    public function permissionsForUser(UserId $userId, ?OrganisationIdInterface $organisationId = null): array
    {
        $sql = 'SELECT DISTINCT p.code FROM user_roles ur'
            .' INNER JOIN role_permissions rp ON rp.role_id = ur.role_id'
            .' INNER JOIN permissions p ON p.id = rp.permission_id'
            .' WHERE ur.user_id = :user_id';
        $params = ['user_id' => $userId->value()];

        if (null === $organisationId) {
            $sql .= ' AND ur.organisation_id IS NULL';
        } else {
            $sql .= ' AND (ur.organisation_id IS NULL OR ur.organisation_id = :organisation_id)';
            $params['organisation_id'] = $organisationId->value();
        }

        $sql .= ' ORDER BY p.code';

        /** @var list<string> $codes */
        $codes = $this->connection->fetchFirstColumn($sql, $params);

        return array_map(static fn (string $code): PermissionCode => PermissionCode::fromString($code), $codes);
    }

    public function assign(
        UserId $userId,
        RoleIdentifier $role,
        ?OrganisationIdInterface $organisationId,
        ?UserId $assignedBy,
        \DateTimeImmutable $assignedAt,
    ): void {
        $roleId = $this->findIdByCode($role) ?? throw RoleNotFoundException::forCode($role->value());

        $organisationValue = $organisationId?->value();
        $params = [
            'user_id' => $userId->value(),
            'role_id' => $roleId,
        ];

        if (null === $organisationValue) {
            $sql = 'SELECT 1 FROM user_roles WHERE user_id = :user_id AND role_id = :role_id AND organisation_id IS NULL';
        } else {
            $sql = 'SELECT 1 FROM user_roles WHERE user_id = :user_id AND role_id = :role_id AND organisation_id = :organisation_id';
            $params['organisation_id'] = $organisationValue;
        }

        if (false !== $this->connection->fetchOne($sql, $params)) {
            return;
        }

        $this->connection->insert('user_roles', [
            'id' => $this->uuid->generate(),
            'user_id' => $userId->value(),
            'role_id' => $roleId,
            'organisation_id' => $organisationValue,
            'assigned_by' => $assignedBy?->value(),
            'assigned_at' => DateTimeCodec::toDatabase($assignedAt),
        ]);
    }
}
