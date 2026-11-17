<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Contract;

use SovereignStack\Lms\Organisation\Application\Contract\UserOrganisationsInterface as OrganisationUserOrganisationsInterface;

/**
 * Bridges the organisation directory contract to the identity-owned contract so
 * identity controllers never import the organisation module directly.
 */
final readonly class UserOrganisationsAdapter implements UserOrganisationsInterface
{
    public function __construct(
        private OrganisationUserOrganisationsInterface $organisations,
    ) {}

    public function forUser(string $userId): array
    {
        return $this->organisations->forUser($userId);
    }
}
