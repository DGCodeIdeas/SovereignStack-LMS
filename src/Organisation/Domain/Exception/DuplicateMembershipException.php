<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Domain\Exception;

use SovereignStack\Lms\Shared\Application\Exception\ConflictException;

final class DuplicateMembershipException extends ConflictException
{
    public static function forOrganisationAndUser(string $organisationId, string $userId): self
    {
        return new self(sprintf('User "%s" is already a member of organisation "%s".', $userId, $organisationId));
    }
}
