<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Domain\Repository;

use SovereignStack\Lms\Organisation\Domain\Entity\Membership;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdInterface;

interface MembershipRepositoryInterface
{
    public function save(Membership $membership): void;

    public function findByOrganisationAndUser(OrganisationId $organisationId, UserIdInterface $userId): ?Membership;

    /**
     * @return list<Membership>
     */
    public function listForUser(UserIdInterface $userId): array;

    /**
     * @return list<Membership>
     */
    public function listForOrganisation(OrganisationId $organisationId): array;
}
