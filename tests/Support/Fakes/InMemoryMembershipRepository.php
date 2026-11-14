<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Support\Fakes;

use SovereignStack\Lms\Organisation\Domain\Entity\Membership;
use SovereignStack\Lms\Organisation\Domain\Repository\MembershipRepositoryInterface;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdInterface;

final class InMemoryMembershipRepository implements MembershipRepositoryInterface
{
    /**
     * @var array<string, Membership>
     */
    private array $memberships = [];

    public function save(Membership $membership): void
    {
        $this->memberships[$membership->id()] = $membership;
    }

    public function findByOrganisationAndUser(OrganisationId $organisationId, UserIdInterface $userId): ?Membership
    {
        foreach ($this->memberships as $membership) {
            if ($membership->organisationId()->equals($organisationId) && $membership->userId() === $userId->value()) {
                return $membership;
            }
        }

        return null;
    }

    public function listForUser(UserIdInterface $userId): array
    {
        return array_values(array_filter(
            $this->memberships,
            static fn (Membership $membership): bool => $membership->userId() === $userId->value(),
        ));
    }

    public function listForOrganisation(OrganisationId $organisationId): array
    {
        return array_values(array_filter(
            $this->memberships,
            static fn (Membership $membership): bool => $membership->organisationId()->equals($organisationId),
        ));
    }

    public function count(): int
    {
        return \count($this->memberships);
    }
}
