<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Handler;

use SovereignStack\Lms\Identity\Application\Command\SuspendUserCommand;
use SovereignStack\Lms\Identity\Application\Contract\PermissionCheckerInterface;
use SovereignStack\Lms\Identity\Domain\Entity\User;
use SovereignStack\Lms\Identity\Domain\Enum\UserStatus;
use SovereignStack\Lms\Identity\Domain\Exception\LastPlatformAdministratorException;
use SovereignStack\Lms\Identity\Domain\Exception\UserNotFoundException;
use SovereignStack\Lms\Identity\Domain\Repository\RoleRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\Repository\UserRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\RoleIdentifier;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;

final readonly class SuspendUserHandler
{
    public function __construct(
        private PermissionCheckerInterface $permissions,
        private UserRepositoryInterface $users,
        private RoleRepositoryInterface $roles,
        private AuditLoggerInterface $audit,
        private TransactionManagerInterface $transactions,
        private ClockInterface $clock,
    ) {}

    public function __invoke(SuspendUserCommand $command): bool
    {
        $this->permissions->require($command->actorUserId, 'user.manage');

        $targetId = $this->userId($command->targetUserId);

        return $this->transactions->transactional(function () use ($command, $targetId): bool {
            $this->roles->lockRole(RoleIdentifier::fromString(RoleIdentifier::PLATFORM_ADMIN));

            $user = $this->users->findById($targetId)
                ?? throw UserNotFoundException::forId($command->targetUserId);

            if (UserStatus::ACTIVE !== $user->status()) {
                return false;
            }

            if ($this->isGlobalPlatformAdmin($user)
                && $this->roles->countActiveUsersWithRole(RoleIdentifier::fromString(RoleIdentifier::PLATFORM_ADMIN)) <= 1) {
                throw LastPlatformAdministratorException::cannotSuspend();
            }

            $user->suspend($this->clock->now());
            $this->users->save($user);
            $this->audit->record('user.suspended', $command->actorUserId, null, 'user', $targetId->value());

            return true;
        });
    }

    private function isGlobalPlatformAdmin(User $user): bool
    {
        foreach ($this->roles->rolesForUser($user->id()) as $role) {
            if (RoleIdentifier::PLATFORM_ADMIN === $role->value()) {
                return true;
            }
        }

        return false;
    }

    private function userId(string $value): UserId
    {
        try {
            return UserId::fromString($value);
        } catch (\InvalidArgumentException) {
            throw UserNotFoundException::forId($value);
        }
    }
}
