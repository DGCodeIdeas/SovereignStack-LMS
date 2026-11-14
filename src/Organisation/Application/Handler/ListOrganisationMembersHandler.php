<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Application\Handler;

use SovereignStack\Lms\Organisation\Application\Contract\PermissionCheckerInterface;
use SovereignStack\Lms\Organisation\Application\Query\ListOrganisationMembersQuery;
use SovereignStack\Lms\Organisation\Domain\Entity\Membership;
use SovereignStack\Lms\Organisation\Domain\Exception\OrganisationNotFoundException;
use SovereignStack\Lms\Organisation\Domain\Repository\MembershipRepositoryInterface;
use SovereignStack\Lms\Organisation\Domain\Repository\OrganisationRepositoryInterface;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;

final readonly class ListOrganisationMembersHandler
{
    public function __construct(
        private PermissionCheckerInterface $permissions,
        private OrganisationRepositoryInterface $organisations,
        private MembershipRepositoryInterface $memberships,
    ) {}

    /**
     * @return list<array{
     *     id: string,
     *     userId: string,
     *     status: string,
     *     joinedAt: null|string,
     *     endedAt: null|string
     * }>
     */
    public function __invoke(ListOrganisationMembersQuery $query): array
    {
        $this->permissions->require($query->actorUserId, 'organisation.members.view', $query->organisationId);

        try {
            $organisationId = OrganisationId::fromString($query->organisationId);
        } catch (\InvalidArgumentException) {
            throw OrganisationNotFoundException::forId($query->organisationId);
        }

        if (null === $this->organisations->findById($organisationId)) {
            throw OrganisationNotFoundException::forId($query->organisationId);
        }

        return array_map(
            static fn (Membership $membership): array => [
                'id' => $membership->id(),
                'userId' => $membership->userId(),
                'status' => $membership->status()->value,
                'joinedAt' => $membership->joinedAt()?->format(\DATE_ATOM),
                'endedAt' => $membership->endedAt()?->format(\DATE_ATOM),
            ],
            $this->memberships->listForOrganisation($organisationId),
        );
    }
}
