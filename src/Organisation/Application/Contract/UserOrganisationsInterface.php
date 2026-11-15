<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Application\Contract;

/**
 * Read-only organisation directory for presentation layers in other modules.
 *
 * Other modules depend on this contract instead of the organisation
 * repositories so the organisation schema stays owned by this module.
 */
interface UserOrganisationsInterface
{
    /**
     * @return list<array{id: string, name: string, slug: string}>
     */
    public function forUser(string $userId): array;
}
