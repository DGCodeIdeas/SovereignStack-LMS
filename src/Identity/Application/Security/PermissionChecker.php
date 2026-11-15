<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Security;

use SovereignStack\Lms\Identity\Application\Contract\PermissionCheckerInterface;
use SovereignStack\Lms\Identity\Domain\Repository\RoleRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\PermissionCode;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;

final readonly class PermissionChecker implements PermissionCheckerInterface
{
    public function __construct(
        private RoleRepositoryInterface $roles,
    ) {}

    public function can(string $userId, string $permission, ?string $organisationId = null): bool
    {
        try {
            $user = UserId::fromString($userId);
            $code = PermissionCode::fromString($permission);
            $organisation = null === $organisationId
                ? null
                : OrganisationIdReference::fromString($organisationId);
        } catch (\InvalidArgumentException) {
            return false;
        }

        foreach ($this->roles->permissionsForUser($user, $organisation) as $granted) {
            if ($granted->equals($code)) {
                return true;
            }
        }

        return false;
    }

    public function require(string $userId, string $permission, ?string $organisationId = null): void
    {
        if (!$this->can($userId, $permission, $organisationId)) {
            throw new ForbiddenException();
        }
    }
}
