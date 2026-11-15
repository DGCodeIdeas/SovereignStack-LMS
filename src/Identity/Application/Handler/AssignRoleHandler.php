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
    ) {}

    public function __invoke(AssignRoleCommand $command): void
    {
        $this->permissions->require($command->actorUserId, 'user.roles.assign', $command->organisationId);

        $targetId = $this->userId($command->targetUserId);

        if (null === $this->users->findById($targetId)) {
            throw UserNotFoundException::forId($command->targetUserId);
        }

        $role = $this->role($command->roleCode);
        $organisation = null === $command->organisationId
            ? null
            : OrganisationIdReference::fromString($command->organisationId);

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
