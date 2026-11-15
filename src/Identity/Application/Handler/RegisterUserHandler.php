<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Handler;

use SovereignStack\Lms\Identity\Application\Command\RegisterUserCommand;
use SovereignStack\Lms\Identity\Application\Contract\PasswordHasherInterface;
use SovereignStack\Lms\Identity\Application\Policy\PasswordPolicy;
use SovereignStack\Lms\Identity\Application\Security\UserTokenService;
use SovereignStack\Lms\Identity\Domain\Entity\User;
use SovereignStack\Lms\Identity\Domain\Entity\UserProfile;
use SovereignStack\Lms\Identity\Domain\Enum\TokenPurpose;
use SovereignStack\Lms\Identity\Domain\Exception\EmailAlreadyRegisteredException;
use SovereignStack\Lms\Identity\Domain\Repository\RoleRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\Repository\UserProfileRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\Repository\UserRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Identity\Domain\ValueObject\RoleIdentifier;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Application\Mail\AccountMailerInterface;
use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;

final readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private UserProfileRepositoryInterface $profiles,
        private PasswordHasherInterface $hasher,
        private UserTokenService $tokens,
        private AccountMailerInterface $mailer,
        private AuditLoggerInterface $audit,
        private TransactionManagerInterface $transactions,
        private UuidGeneratorInterface $uuid,
        private ClockInterface $clock,
        private RoleRepositoryInterface $roles,
    ) {}

    public function __invoke(RegisterUserCommand $command): UserId
    {
        $email = $this->normalizeEmail($command->email);
        PasswordPolicy::assertValid($command->plainPassword, $email->value());

        if ($this->users->emailExists($email)) {
            throw EmailAlreadyRegisteredException::forEmail($email->value());
        }

        $now = $this->clock->now();
        $userId = UserId::fromString($this->uuid->generate());

        $user = User::register($userId, $email, $this->hasher->hash($command->plainPassword), $now);
        $profile = UserProfile::create(
            $this->uuid->generate(),
            $userId,
            $this->displayName($command->displayName, $email),
            'UTC',
            [],
            $now,
        );

        $rawToken = $this->transactions->transactional(function () use ($user, $profile, $userId, $now): string {
            $this->users->save($user);
            $this->profiles->save($profile);
            $this->roles->assign(
                $userId,
                RoleIdentifier::fromString(RoleIdentifier::LMS_LEARNER),
                null,
                null,
                $now,
            );

            return $this->tokens->issue($userId, TokenPurpose::EMAIL_VERIFICATION, $now);
        });

        $this->mailer->sendEmailVerification($email, $rawToken);

        $this->audit->record('user.registered', null, null, 'user', $userId->value());

        return $userId;
    }

    private function normalizeEmail(string $email): Email
    {
        try {
            return Email::fromString($email);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withErrors(['email' => [$exception->getMessage()]]);
        }
    }

    private function displayName(string $displayName, Email $email): string
    {
        $trimmed = trim($displayName);

        if ('' !== $trimmed) {
            return $trimmed;
        }

        $localPart = strstr($email->value(), '@', true);

        return false === $localPart || '' === $localPart ? $email->value() : $localPart;
    }
}
