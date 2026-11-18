<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Handler;

use SovereignStack\Lms\Identity\Application\Command\RevokeRoleCommand;
use SovereignStack\Lms\Identity\Application\Contract\PermissionCheckerInterface;
use SovereignStack\Lms\Identity\Domain\Exception\LastPlatformAdministratorException;
use SovereignStack\Lms\Identity\Domain\Exception\UserNotFoundException;
use SovereignStack\Lms\Identity\Domain\Repository\RoleRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\Repository\UserRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\PermissionCode;
use SovereignStack\Lms\Identity\Domain\ValueObject\RoleIdentifier;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;

final readonly class RevokeRoleHandler
{
    public function __construct(
        private PermissionCheckerInterface $permissions,
        private UserRepositoryInterface $users,
        private RoleRepositoryInterface $roles,
        private AuditLoggerInterface $audit,
        private TransactionManagerInterface $transactions,
    ) {}

    public function __invoke(RevokeRoleCommand $command): void
    {
        $this->permissions->require($command->actorUserId, PermissionCode::USER_ROLES_ASSIGN, $command->organisationId);

        $targetId = $this->userId($command->targetUserId);
        $role = $this->role($command->roleCode);
        $organisation = $this->organisation($command->organisationId);

        $this->transactions->transactional(function () use ($command, $targetId, $role, $organisation): void {
            // The lock must be the first statement in the transaction. A plain
            // SELECT before it would establish a REPEATABLE READ snapshot and
            // make the later count stale.
            $this->roles->lockRole($role);

            if (null === $this->users->findById($targetId)) {
                throw UserNotFoundException::forId($command->targetUserId);
            }

            if (!$this->hasAssignment($targetId, $role, $organisation)) {
                return;
            }

            if (null === $organisation
                && RoleIdentifier::PLATFORM_ADMIN === $role->value()
                && $this->roles->countActiveUsersWithRole($role) <= 1) {
                throw LastPlatformAdministratorException::cannotRevoke();
            }

            $this->roles->revoke($targetId, $role, $organisation);

            $this->audit->record(
                'user.role_revoked',
                $command->actorUserId,
                $command->organisationId,
                'user',
                $targetId->value(),
                ['role' => $role->value(), 'organisation_id' => $command->organisationId],
            );
        });
    }

    private function userId(string $value): UserId
    {
        try {
            return UserId::fromString($value);
        } catch (\InvalidArgumentException) {
            throw UserNotFoundException::forId($value);
        }
    }

    private function role(string $code): RoleIdentifier
    {
        try {
            return RoleIdentifier::fromString($code);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withErrors(['role' => [$exception->getMessage()]]);
        }
    }

    private function organisation(?string $value): ?OrganisationIdInterface
    {
        if (null === $value) {
            return null;
        }

        try {
            return OrganisationIdReference::fromString($value);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withErrors(['organisation' => [$exception->getMessage()]]);
        }
    }

    private function hasAssignment(
        UserId $userId,
        RoleIdentifier $role,
        ?OrganisationIdInterface $organisation,
    ): bool {
        $organisationValue = $organisation?->value();

        foreach ($this->roles->assignmentsForUser($userId) as $assignment) {
            if ($assignment['roleCode'] === $role->value()
                && $assignment['organisationId'] === $organisationValue) {
                return true;
            }
        }

        return false;
    }
}
