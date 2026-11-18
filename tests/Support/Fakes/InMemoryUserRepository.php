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

    /**
     * @var array<string, string>
     */
    private array $displayNames = [];

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

    public function setDisplayName(UserId $userId, string $displayName): void
    {
        $this->displayNames[$userId->value()] = $displayName;
    }

    public function search(string $query, int $limit, int $offset): array
    {
        $matches = $this->matching($query);
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $rows = [];

        foreach (\array_slice($matches, $offset, $limit) as $user) {
            $rows[] = [
                'id' => $user->id()->value(),
                'email' => $user->email()->value(),
                'status' => $user->status()->value,
                'displayName' => $this->displayNames[$user->id()->value()] ?? '',
                'createdAt' => $user->createdAt()->format(\DATE_ATOM),
                'lastLoginAt' => $user->lastLoginAt()?->format(\DATE_ATOM),
            ];
        }

        return $rows;
    }

    public function countSearch(string $query): int
    {
        return \count($this->matching($query));
    }

    /**
     * @return list<User>
     */
    private function matching(string $query): array
    {
        $term = mb_strtolower(trim($query));
        $matches = [];

        foreach ($this->users as $user) {
            $displayName = mb_strtolower($this->displayNames[$user->id()->value()] ?? '');

            if ('' !== $term
                && !str_contains($user->email()->value(), $term)
                && !str_contains($displayName, $term)) {
                continue;
            }

            $matches[] = $user;
        }

        usort(
            $matches,
            static function (User $left, User $right): int {
                $byCreatedAt = $right->createdAt() <=> $left->createdAt();

                return 0 !== $byCreatedAt ? $byCreatedAt : strcmp($right->id()->value(), $left->id()->value());
            },
        );

        return $matches;
    }
}
