<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Contract;

use SovereignStack\Lms\Organisation\Application\Contract\MembershipCheckerInterface as OrganisationMembershipCheckerInterface;

/**
 * Bridges the organisation membership directory to the enrollment-owned
 * contract so enrollment handlers never import the organisation module directly.
 */
final readonly class MembershipCheckerAdapter implements MembershipInterface
{
    public function __construct(
        private OrganisationMembershipCheckerInterface $memberships,
    ) {}

    public function isActiveMember(string $userId, string $organisationId): bool
    {
        return $this->memberships->isActiveMember($userId, $organisationId);
    }
}
