<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Infrastructure\Security;

use SovereignStack\Lms\Identity\Domain\Entity\User;
use SovereignStack\Lms\Identity\Domain\Enum\UserStatus;
use SovereignStack\Lms\Identity\Domain\Repository\RoleRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\Repository\UserRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<SecurityUser>
 */
final readonly class DbalUserProvider implements UserProviderInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
        private RoleRepositoryInterface $roles,
        private ClockInterface $clock,
    ) {}

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $email = $this->normalizeEmail($identifier);

        if (null === $email) {
            throw $this->notFound($identifier);
        }

        $user = $this->users->findByEmail($email);

        if (null === $user) {
            throw $this->notFound($identifier);
        }

        $this->assertCanAuthenticate($user);

        return $this->build($user);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(sprintf('Unsupported user class "%s".', $user::class));
        }

        $reloaded = $this->users->findById($user->getDomainUser()->id());

        if (null === $reloaded) {
            throw $this->notFound($user->getUserIdentifier());
        }

        $this->assertCanAuthenticate($reloaded);

        return $this->build($reloaded);
    }

    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class;
    }

    private function build(User $user): SecurityUser
    {
        return new SecurityUser(
            $user,
            $this->roles->rolesForUser($user->id()),
            $this->roles->permissionsForUser($user->id()),
        );
    }

    private function assertCanAuthenticate(User $user): void
    {
        if (UserStatus::PENDING_VERIFICATION === $user->status()) {
            throw new CustomUserMessageAccountStatusException('Please verify your email address or contact an administrator.');
        }

        if ($user->isLocked($this->clock->now())) {
            throw new CustomUserMessageAccountStatusException('Account is temporarily locked.');
        }

        if (UserStatus::ACTIVE !== $user->status()) {
            throw new CustomUserMessageAccountStatusException('This account is not active. Please contact an administrator.');
        }
    }

    private function normalizeEmail(string $identifier): ?Email
    {
        try {
            return Email::fromString($identifier);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function notFound(string $identifier): UserNotFoundException
    {
        $exception = new UserNotFoundException();
        $exception->setUserIdentifier($identifier);

        return $exception;
    }
}
