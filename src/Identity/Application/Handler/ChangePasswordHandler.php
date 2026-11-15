<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Handler;

use SovereignStack\Lms\Identity\Application\Command\ChangePasswordCommand;
use SovereignStack\Lms\Identity\Application\Contract\PasswordHasherInterface;
use SovereignStack\Lms\Identity\Application\Policy\PasswordPolicy;
use SovereignStack\Lms\Identity\Domain\Exception\UserNotFoundException;
use SovereignStack\Lms\Identity\Domain\Repository\UserRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;

final readonly class ChangePasswordHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordHasherInterface $hasher,
        private AuditLoggerInterface $audit,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ChangePasswordCommand $command): void
    {
        $userId = $this->stringToUserId($command->userId);
        $user = $this->users->findById($userId) ?? throw UserNotFoundException::forId($command->userId);

        if (!$this->hasher->verify($user->passwordHash(), $command->currentPassword)) {
            throw new ForbiddenException('The current password is incorrect.');
        }

        PasswordPolicy::assertValid($command->newPassword, $user->email()->value());

        $user->changePassword($this->hasher->hash($command->newPassword), $this->clock->now());
        $this->users->save($user);

        $this->audit->record('user.password_changed', $userId->value(), null, 'user', $userId->value());
    }

    private function stringToUserId(string $value): UserId
    {
        try {
            return UserId::fromString($value);
        } catch (\InvalidArgumentException) {
            throw UserNotFoundException::forId($value);
        }
    }
}
