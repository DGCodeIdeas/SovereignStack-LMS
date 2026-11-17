<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Support\Fakes;

use SovereignStack\Lms\Enrollment\Application\Contract\PermissionCheckerInterface as EnrollmentPermissionCheckerInterface;
use SovereignStack\Lms\Organisation\Application\Contract\PermissionCheckerInterface;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;

final class FakePermissionChecker implements EnrollmentPermissionCheckerInterface, PermissionCheckerInterface
{
    /**
     * @param list<string> $denied
     */
    public function __construct(
        private array $denied = [],
    ) {}

    public function can(string $userId, string $permission, ?string $organisationId = null): bool
    {
        return !\in_array($permission, $this->denied, true);
    }

    public function require(string $userId, string $permission, ?string $organisationId = null): void
    {
        if (!$this->can($userId, $permission, $organisationId)) {
            throw new ForbiddenException();
        }
    }
}
