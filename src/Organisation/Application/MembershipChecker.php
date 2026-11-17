<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Application;

use SovereignStack\Lms\Organisation\Application\Contract\MembershipCheckerInterface;
use SovereignStack\Lms\Organisation\Domain\Enum\MembershipStatus;
use SovereignStack\Lms\Organisation\Domain\Repository\MembershipRepositoryInterface;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;

final readonly class MembershipChecker implements MembershipCheckerInterface
{
    public function __construct(
        private MembershipRepositoryInterface $memberships,
    ) {}

    public function isActiveMember(string $userId, string $organisationId): bool
    {
        try {
            $organisation = OrganisationId::fromString($organisationId);
            $user = UserIdReference::fromString($userId);
        } catch (\InvalidArgumentException) {
            return false;
        }

        $membership = $this->memberships->findByOrganisationAndUser($organisation, $user);

        return null !== $membership && MembershipStatus::ACTIVE === $membership->status();
    }
}
