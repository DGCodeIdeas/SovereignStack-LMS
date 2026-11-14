<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Application\Handler;

use SovereignStack\Lms\Organisation\Application\Command\AddMembershipCommand;
use SovereignStack\Lms\Organisation\Application\Contract\PermissionCheckerInterface;
use SovereignStack\Lms\Organisation\Domain\Entity\Membership;
use SovereignStack\Lms\Organisation\Domain\Enum\MembershipStatus;
use SovereignStack\Lms\Organisation\Domain\Exception\DuplicateMembershipException;
use SovereignStack\Lms\Organisation\Domain\Exception\OrganisationNotFoundException;
use SovereignStack\Lms\Organisation\Domain\Repository\MembershipRepositoryInterface;
use SovereignStack\Lms\Organisation\Domain\Repository\OrganisationRepositoryInterface;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;

final readonly class AddMembershipHandler
{
    public function __construct(
        private PermissionCheckerInterface $permissions,
        private OrganisationRepositoryInterface $organisations,
        private MembershipRepositoryInterface $memberships,
        private AuditLoggerInterface $audit,
        private UuidGeneratorInterface $uuid,
        private ClockInterface $clock,
    ) {}

    public function __invoke(AddMembershipCommand $command): void
    {
        $this->permissions->require($command->actorUserId, 'organisation.members.manage', $command->organisationId);

        $organisationId = $this->organisationId($command->organisationId);

        if (null === $this->organisations->findById($organisationId)) {
            throw OrganisationNotFoundException::forId($command->organisationId);
        }

        $userId = UserIdReference::fromString($command->userId);
        $existing = $this->memberships->findByOrganisationAndUser($organisationId, $userId);

        if (null !== $existing && MembershipStatus::ACTIVE === $existing->status()) {
            throw DuplicateMembershipException::forOrganisationAndUser($command->organisationId, $command->userId);
        }

        $membership = Membership::join(
            $existing?->id() ?? $this->uuid->generate(),
            $organisationId,
            $userId,
            $this->clock->now(),
        );

        $this->memberships->save($membership);

        $this->audit->record(
            'membership.added',
            $command->actorUserId,
            $command->organisationId,
            'membership',
            $membership->id(),
            ['user_id' => $command->userId],
        );
    }

    private function organisationId(string $value): OrganisationId
    {
        try {
            return OrganisationId::fromString($value);
        } catch (\InvalidArgumentException) {
            throw OrganisationNotFoundException::forId($value);
        }
    }
}
