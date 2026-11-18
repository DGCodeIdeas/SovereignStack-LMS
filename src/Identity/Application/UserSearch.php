<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application;

use SovereignStack\Lms\Identity\Application\Contract\UserSearchInterface;
use SovereignStack\Lms\Identity\Domain\Repository\UserRepositoryInterface;

final readonly class UserSearch implements UserSearchInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {}

    public function search(string $query, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));

        return array_map(
            static fn (array $row): array => [
                'id' => $row['id'],
                'email' => $row['email'],
                'displayName' => $row['displayName'],
            ],
            $this->users->search($query, $limit, 0),
        );
    }
}
