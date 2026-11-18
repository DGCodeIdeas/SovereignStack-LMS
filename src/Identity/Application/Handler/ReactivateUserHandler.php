<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Handler;

use SovereignStack\Lms\Identity\Application\Command\ReactivateUserCommand;
use SovereignStack\Lms\Identity\Application\Contract\PermissionCheckerInterface;
use SovereignStack\Lms\Identity\Domain\Exception\UserNotFoundException;
use SovereignStack\Lms\Identity\Domain\Repository\UserRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;

final readonly class ReactivateUserHandler
{
    public function __construct(
        private PermissionCheckerInterface $permissions,
        private UserRepositoryInterface $users,
        private AuditLoggerInterface $audit,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ReactivateUserCommand $command): void
    {
        $this->permissions->require($command->actorUserId, 'user.manage');

        $targetId = $this->userId($command->targetUserId);
        $user = $this->users->findById($targetId) ?? throw UserNotFoundException::forId($command->targetUserId);

        $user->reactivate($this->clock->now());
        $this->users->save($user);

        $this->audit->record('user.reactivated', $command->actorUserId, null, 'user', $targetId->value());
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
