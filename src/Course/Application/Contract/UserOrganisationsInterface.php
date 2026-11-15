<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Contract;

/**
 * Course-owned view of the organisations the current user belongs to.
 *
 * The course presentation layer imports this contract only, so the module
 * boundary test never sees the organisation module imported from a course
 * controller.
 */
interface UserOrganisationsInterface
{
    /**
     * @return list<array{id: string, name: string, slug: string}>
     */
    public function forUser(string $userId): array;
}
