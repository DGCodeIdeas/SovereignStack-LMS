<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Application\Contract;

use SovereignStack\Lms\Identity\Application\Contract\PermissionCheckerInterface as IdentityPermissionCheckerInterface;

/**
 * Bridges the identity permission checker to the organisation-owned contract so
 * organisation handlers never import the identity module directly.
 */
final readonly class PermissionCheckerAdapter implements PermissionCheckerInterface
{
    public function __construct(
        private IdentityPermissionCheckerInterface $checker,
    ) {}

    public function can(string $userId, string $permission, ?string $organisationId = null): bool
    {
        return $this->checker->can($userId, $permission, $organisationId);
    }

    public function require(string $userId, string $permission, ?string $organisationId = null): void
    {
        $this->checker->require($userId, $permission, $organisationId);
    }
}
