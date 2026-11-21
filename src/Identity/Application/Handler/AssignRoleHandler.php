<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Handler;

use SovereignStack\Lms\Identity\Application\Command\AssignRoleCommand;
use SovereignStack\Lms\Identity\Application\Contract\PermissionCheckerInterface;
use SovereignStack\Lms\Identity\Domain\Exception\UserNotFoundException;
use SovereignStack\Lms\Identity\Domain\Repository\RoleRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\Repository\UserRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\RoleIdentifier;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;

final readonly class AssignRoleHandler
{
    public function __construct(
        private PermissionCheckerInterface $permissions,
        private UserRepositoryInterface $users,
        private RoleRepositoryInterface $roles,
        private AuditLoggerInterface $audit,
        private ClockInterface $clock,
        private TransactionManagerInterface $transactions,
    ) {}

    public function __invoke(AssignRoleCommand $command): void
    {
        $this->permissions->require($command->actorUserId, 'user.roles.assign', $command->organisationId);

        $targetId = $this->userId($command->targetUserId);
        $role = $this->role($command->roleCode);
        $organisation = null === $command->organisationId
            ? null
            : OrganisationIdReference::fromString($command->organisationId);

        if (RoleIdentifier::PLATFORM_ADMIN === $role->value() && null !== $organisation) {
            throw ValidationException::withErrors([
                'role' => ['The platform administrator role must be assigned globally.'],
            ]);
        }

        $this->transactions->transactional(function () use ($command, $targetId, $role, $organisation): void {
            // All role mutations take the role row lock first so they share one
            // lock order with revoke, suspend, deactivate and bootstrap.
            $this->roles->lockRole($role);

            if (null === $this->users->findById($targetId)) {
                throw UserNotFoundException::forId($command->targetUserId);
            }

            $this->roles->assign(
                $targetId,
                $role,
                $organisation,
                $this->userId($command->actorUserId),
                $this->clock->now(),
            );

            $this->audit->record(
                'user.role_assigned',
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
}
