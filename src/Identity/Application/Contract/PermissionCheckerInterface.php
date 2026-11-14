<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Contract;

interface PermissionCheckerInterface
{
    public function can(string $userId, string $permission, ?string $organisationId = null): bool;

    public function require(string $userId, string $permission, ?string $organisationId = null): void;
}
