<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Domain\Exception;

use SovereignStack\Lms\Shared\Application\Exception\NotFoundException;

final class MembershipNotFoundException extends NotFoundException
{
    public static function forOrganisationAndUser(string $organisationId, string $userId): self
    {
        return new self(sprintf('Membership for user "%s" in organisation "%s" was not found.', $userId, $organisationId));
    }
}
