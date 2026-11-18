<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Contract;

/**
 * Course-owned view of the user directory used to suggest instructors.
 *
 * The course presentation layer imports this contract only, so the module
 * boundary test never sees the identity module imported from a course
 * controller.
 */
interface UserSearchInterface
{
    /**
     * @return list<array{id: string, email: string, displayName: string}>
     */
    public function search(string $query, int $limit = 50): array;
}
