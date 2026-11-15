<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Handler;

use SovereignStack\Lms\Identity\Application\Command\ResetPasswordCommand;
use SovereignStack\Lms\Identity\Application\Contract\PasswordHasherInterface;
use SovereignStack\Lms\Identity\Application\Policy\PasswordPolicy;
use SovereignStack\Lms\Identity\Application\Security\UserTokenService;
use SovereignStack\Lms\Identity\Domain\Enum\TokenPurpose;
use SovereignStack\Lms\Identity\Domain\Exception\UserNotFoundException;
use SovereignStack\Lms\Identity\Domain\Repository\UserRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;

final readonly class ResetPasswordHandler
{
    public function __construct(
        private UserTokenService $tokens,
        private UserRepositoryInterface $users,
        private PasswordHasherInterface $hasher,
        private AuditLoggerInterface $audit,
        private TransactionManagerInterface $transactions,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ResetPasswordCommand $command): void
    {
        $userId = $this->transactions->transactional(function () use ($command): UserId {
            $userId = $this->tokens->consume($command->rawToken, TokenPurpose::PASSWORD_RESET);
            $user = $this->users->findById($userId) ?? throw UserNotFoundException::forId($userId->value());

            PasswordPolicy::assertValid($command->plainPassword, $user->email()->value());

            $user->changePassword($this->hasher->hash($command->plainPassword), $this->clock->now());
            $this->users->save($user);

            return $userId;
        });

        $this->audit->record('user.password_reset', $userId->value(), null, 'user', $userId->value());
    }
}
