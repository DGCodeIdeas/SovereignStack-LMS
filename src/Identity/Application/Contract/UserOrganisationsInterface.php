<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Contract;

/**
 * Read-only organisation directory for the identity administration UI.
 *
 * The identity presentation layer depends on this contract instead of the
 * organisation module so module boundaries stay intact.
 */
interface UserOrganisationsInterface
{
    /**
     * @return list<array{id: string, name: string, slug: string}>
     */
    public function forUser(string $userId): array;
}
