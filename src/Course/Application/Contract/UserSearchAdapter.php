<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Contract;

use SovereignStack\Lms\Identity\Application\Contract\UserSearchInterface as IdentityUserSearchInterface;

/**
 * Bridges the identity user-search contract to the course-owned contract so
 * course controllers never import the identity module directly.
 */
final readonly class UserSearchAdapter implements UserSearchInterface
{
    public function __construct(
        private IdentityUserSearchInterface $users,
    ) {}

    public function search(string $query, int $limit = 50): array
    {
        return $this->users->search($query, $limit);
    }
}
