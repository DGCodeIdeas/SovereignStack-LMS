<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Contract;

interface UserSearchInterface
{
    /**
     * @return list<array{id: string, email: string, displayName: string}>
     */
    public function search(string $query, int $limit = 50): array;
}
