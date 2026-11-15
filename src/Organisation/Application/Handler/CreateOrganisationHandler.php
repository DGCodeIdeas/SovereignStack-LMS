<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Application\Handler;

use SovereignStack\Lms\Organisation\Application\Command\CreateOrganisationCommand;
use SovereignStack\Lms\Organisation\Application\Contract\PermissionCheckerInterface;
use SovereignStack\Lms\Organisation\Domain\Entity\Membership;
use SovereignStack\Lms\Organisation\Domain\Entity\Organisation;
use SovereignStack\Lms\Organisation\Domain\Exception\DuplicateOrganisationSlugException;
use SovereignStack\Lms\Organisation\Domain\Repository\MembershipRepositoryInterface;
use SovereignStack\Lms\Organisation\Domain\Repository\OrganisationRepositoryInterface;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;

final readonly class CreateOrganisationHandler
{
    public function __construct(
        private PermissionCheckerInterface $permissions,
        private OrganisationRepositoryInterface $organisations,
        private MembershipRepositoryInterface $memberships,
        private AuditLoggerInterface $audit,
        private TransactionManagerInterface $transactions,
        private UuidGeneratorInterface $uuid,
        private ClockInterface $clock,
    ) {}

    public function __invoke(CreateOrganisationCommand $command): OrganisationId
    {
        $this->permissions->require($command->actorUserId, 'user.manage');

        $slug = strtolower(trim($command->slug));

        if (null !== $this->organisations->findBySlug($slug)) {
            throw DuplicateOrganisationSlugException::forSlug($slug);
        }

        $organisation = $this->create($command->name, $slug);

        $this->transactions->transactional(function () use ($organisation, $command): void {
            $this->organisations->save($organisation);
            $this->assignCreatorMembership($organisation, $command->actorUserId);
        });

        $this->audit->record(
            'organisation.created',
            $command->actorUserId,
            $organisation->id()->value(),
            'organisation',
            $organisation->id()->value(),
            ['slug' => $organisation->slug()],
        );

        return $organisation->id();
    }

    private function assignCreatorMembership(Organisation $organisation, string $actorUserId): void
    {
        $actor = UserIdReference::fromString($actorUserId);

        if (null !== $this->memberships->findByOrganisationAndUser($organisation->id(), $actor)) {
            return;
        }

        $this->memberships->save(Membership::join(
            $this->uuid->generate(),
            $organisation->id(),
            $actor,
            $this->clock->now(),
        ));
    }

    private function create(string $name, string $slug): Organisation
    {
        try {
            return Organisation::create(
                OrganisationId::fromString($this->uuid->generate()),
                $name,
                $slug,
                $this->clock->now(),
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withErrors(['organisation' => [$exception->getMessage()]]);
        }
    }
}
