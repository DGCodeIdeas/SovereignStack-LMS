<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Application\Handler;

use SovereignStack\Lms\Organisation\Application\Command\EndMembershipCommand;
use SovereignStack\Lms\Organisation\Application\Contract\PermissionCheckerInterface;
use SovereignStack\Lms\Organisation\Domain\Exception\MembershipNotFoundException;
use SovereignStack\Lms\Organisation\Domain\Exception\OrganisationNotFoundException;
use SovereignStack\Lms\Organisation\Domain\Repository\MembershipRepositoryInterface;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;

final readonly class EndMembershipHandler
{
    public function __construct(
        private PermissionCheckerInterface $permissions,
        private MembershipRepositoryInterface $memberships,
        private AuditLoggerInterface $audit,
        private ClockInterface $clock,
    ) {}

    public function __invoke(EndMembershipCommand $command): void
    {
        $this->permissions->require($command->actorUserId, 'organisation.members.manage', $command->organisationId);

        $organisationId = $this->organisationId($command->organisationId);
        $membership = $this->memberships->findByOrganisationAndUser(
            $organisationId,
            UserIdReference::fromString($command->userId),
        ) ?? throw MembershipNotFoundException::forOrganisationAndUser($command->organisationId, $command->userId);

        $membership->end($this->clock->now());
        $this->memberships->save($membership);

        $this->audit->record(
            'membership.ended',
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
