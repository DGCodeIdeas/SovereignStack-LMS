<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Support\Fakes;

use SovereignStack\Lms\Identity\Domain\Entity\User;
use SovereignStack\Lms\Identity\Domain\Repository\UserRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /**
     * @var array<string, User>
     */
    private array $users = [];

    public function save(User $user): void
    {
        $this->users[$user->id()->value()] = $user;
    }

    public function updateLoginState(User $user): void
    {
        $this->save($user);
    }

    public function findById(UserId $id): ?User
    {
        return $this->users[$id->value()] ?? null;
    }

    public function findByEmail(Email $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email()->equals($email)) {
                return $user;
            }
        }

        return null;
    }

    public function emailExists(Email $email): bool
    {
        return null !== $this->findByEmail($email);
    }
}
