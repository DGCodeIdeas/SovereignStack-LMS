<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Handler;

use SovereignStack\Lms\Identity\Application\Command\RequestPasswordResetCommand;
use SovereignStack\Lms\Identity\Application\Security\UserTokenService;
use SovereignStack\Lms\Identity\Domain\Enum\TokenPurpose;
use SovereignStack\Lms\Identity\Domain\Enum\UserStatus;
use SovereignStack\Lms\Identity\Domain\Repository\UserRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Shared\Application\Mail\AccountMailerInterface;

final readonly class RequestPasswordResetHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private UserTokenService $tokens,
        private AccountMailerInterface $mailer,
    ) {}

    public function __invoke(RequestPasswordResetCommand $command): void
    {
        try {
            $email = Email::fromString($command->email);
        } catch (\InvalidArgumentException) {
            return;
        }

        $user = $this->users->findByEmail($email);

        if (null === $user || UserStatus::ACTIVE !== $user->status()) {
            return;
        }

        $rawToken = $this->tokens->issue($user->id(), TokenPurpose::PASSWORD_RESET);
        $this->mailer->sendPasswordReset($email, $rawToken);
    }
}
