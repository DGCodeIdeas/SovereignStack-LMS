<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Organisation\Application;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Organisation\Application\Command\AddMembershipCommand;
use SovereignStack\Lms\Organisation\Application\Command\CreateOrganisationCommand;
use SovereignStack\Lms\Organisation\Application\Command\EndMembershipCommand;
use SovereignStack\Lms\Organisation\Application\Handler\AddMembershipHandler;
use SovereignStack\Lms\Organisation\Application\Handler\CreateOrganisationHandler;
use SovereignStack\Lms\Organisation\Application\Handler\EndMembershipHandler;
use SovereignStack\Lms\Organisation\Application\Handler\ListOrganisationMembersHandler;
use SovereignStack\Lms\Organisation\Application\Query\ListOrganisationMembersQuery;
use SovereignStack\Lms\Organisation\Domain\Entity\Organisation;
use SovereignStack\Lms\Organisation\Domain\Enum\MembershipStatus;
use SovereignStack\Lms\Organisation\Domain\Exception\DuplicateMembershipException;
use SovereignStack\Lms\Organisation\Domain\Exception\DuplicateOrganisationSlugException;
use SovereignStack\Lms\Organisation\Domain\Exception\MembershipNotFoundException;
use SovereignStack\Lms\Organisation\Domain\Exception\OrganisationNotFoundException;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use SovereignStack\Lms\Tests\Support\Fakes\FakePermissionChecker;
use SovereignStack\Lms\Tests\Support\Fakes\FakeTransactionManager;
use SovereignStack\Lms\Tests\Support\Fakes\FixedClock;
use SovereignStack\Lms\Tests\Support\Fakes\InMemoryMembershipRepository;
use SovereignStack\Lms\Tests\Support\Fakes\InMemoryOrganisationRepository;
use SovereignStack\Lms\Tests\Support\Fakes\RecordingAuditLogger;
use SovereignStack\Lms\Tests\Support\Fakes\SequentialUuidGenerator;

/**
 * @internal
 *
 * @coversNothing
 */
final class OrganisationHandlersTest extends TestCase
{
    private const ACTOR = '01900000-0000-7000-8000-000000000900';
    private const USER = '01900000-0000-7000-8000-000000000001';
    private const ORGANISATION = '01900000-0000-7000-8000-000000000100';

    private FixedClock $clock;
    private InMemoryOrganisationRepository $organisations;
    private InMemoryMembershipRepository $memberships;
    private RecordingAuditLogger $audit;
    private FakeTransactionManager $transactions;

    protected function setUp(): void
    {
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-11-14T10:00:00+00:00'));
        $this->organisations = new InMemoryOrganisationRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->audit = new RecordingAuditLogger();
        $this->transactions = new FakeTransactionManager();
    }

    public function testCreateOrganisationRejectsDuplicateSlug(): void
    {
        $this->seedOrganisation();

        $handler = new CreateOrganisationHandler(
            new FakePermissionChecker(),
            $this->organisations,
            $this->memberships,
            $this->audit,
            $this->transactions,
            new SequentialUuidGenerator(),
            $this->clock,
        );

        $this->expectException(DuplicateOrganisationSlugException::class);

        ($handler)(new CreateOrganisationCommand(self::ACTOR, 'Acme', 'acme'));
    }

    public function testCreateOrganisationSucceedsAndAudits(): void
    {
        $handler = new CreateOrganisationHandler(
            new FakePermissionChecker(),
            $this->organisations,
            $this->memberships,
            $this->audit,
            $this->transactions,
            new SequentialUuidGenerator(),
            $this->clock,
        );

        $organisationId = ($handler)(new CreateOrganisationCommand(self::ACTOR, 'Globex', 'globex'));

        self::assertNotNull($this->organisations->findBySlug('globex'));
        self::assertTrue($this->audit->hasEvent('organisation.created'));
        self::assertSame(1, $this->transactions->calls);
        self::assertSame('globex', $this->organisations->findById($organisationId)?->slug());

        $creatorMembership = $this->memberships->findByOrganisationAndUser(
            $organisationId,
            UserIdReference::fromString(self::ACTOR),
        );

        self::assertNotNull($creatorMembership);
        self::assertSame(MembershipStatus::ACTIVE, $creatorMembership->status());
        self::assertSame(self::ACTOR, $creatorMembership->userId());
    }

    public function testCreateOrganisationRequiresPermission(): void
    {
        $handler = new CreateOrganisationHandler(
            new FakePermissionChecker(['user.manage']),
            $this->organisations,
            $this->memberships,
            $this->audit,
            $this->transactions,
            new SequentialUuidGenerator(),
            $this->clock,
        );

        $this->expectException(ForbiddenException::class);

        ($handler)(new CreateOrganisationCommand(self::ACTOR, 'Globex', 'globex'));
    }

    public function testAddMembershipSucceedsAndAudits(): void
    {
        $this->seedOrganisation();

        ($this->addHandler())(new AddMembershipCommand(self::ACTOR, self::ORGANISATION, self::USER));

        $membership = $this->memberships->findByOrganisationAndUser(
            OrganisationId::fromString(self::ORGANISATION),
            UserIdReference::fromString(self::USER),
        );

        self::assertNotNull($membership);
        self::assertSame(MembershipStatus::ACTIVE, $membership->status());
        self::assertTrue($this->audit->hasEvent('membership.added'));
    }

    public function testAddMembershipRejectsDuplicateActiveMembership(): void
    {
        $this->seedOrganisation();
        $handler = $this->addHandler();
        ($handler)(new AddMembershipCommand(self::ACTOR, self::ORGANISATION, self::USER));

        $this->expectException(DuplicateMembershipException::class);

        ($handler)(new AddMembershipCommand(self::ACTOR, self::ORGANISATION, self::USER));
    }

    public function testAddMembershipRequiresExistingOrganisation(): void
    {
        $this->expectException(OrganisationNotFoundException::class);

        ($this->addHandler())(new AddMembershipCommand(self::ACTOR, self::ORGANISATION, self::USER));
    }

    public function testAddMembershipRequiresScopedPermission(): void
    {
        $this->seedOrganisation();

        $handler = new AddMembershipHandler(
            new FakePermissionChecker(['organisation.members.manage']),
            $this->organisations,
            $this->memberships,
            $this->audit,
            new SequentialUuidGenerator(),
            $this->clock,
        );

        $this->expectException(ForbiddenException::class);

        ($handler)(new AddMembershipCommand(self::ACTOR, self::ORGANISATION, self::USER));
    }

    public function testEndMembershipRequiresExistingMembership(): void
    {
        $handler = new EndMembershipHandler(new FakePermissionChecker(), $this->memberships, $this->audit, $this->clock);

        $this->expectException(MembershipNotFoundException::class);

        ($handler)(new EndMembershipCommand(self::ACTOR, self::ORGANISATION, self::USER));
    }

    public function testEndMembershipMarksEndedAndAudits(): void
    {
        $this->seedOrganisation();
        ($this->addHandler())(new AddMembershipCommand(self::ACTOR, self::ORGANISATION, self::USER));

        $handler = new EndMembershipHandler(new FakePermissionChecker(), $this->memberships, $this->audit, $this->clock);
        ($handler)(new EndMembershipCommand(self::ACTOR, self::ORGANISATION, self::USER));

        $membership = $this->memberships->findByOrganisationAndUser(
            OrganisationId::fromString(self::ORGANISATION),
            UserIdReference::fromString(self::USER),
        );

        self::assertNotNull($membership);
        self::assertSame(MembershipStatus::ENDED, $membership->status());
        self::assertNotNull($membership->endedAt());
        self::assertTrue($this->audit->hasEvent('membership.ended'));
    }

    public function testListMembersIsDeniedWithoutScopedPermission(): void
    {
        $this->seedOrganisation();

        $handler = new ListOrganisationMembersHandler(
            new FakePermissionChecker(['organisation.members.view']),
            $this->organisations,
            $this->memberships,
        );

        $this->expectException(ForbiddenException::class);

        ($handler)(new ListOrganisationMembersQuery(self::ACTOR, self::ORGANISATION));
    }

    public function testListMembersReturnsMembershipsWithinOrganisation(): void
    {
        $this->seedOrganisation();
        ($this->addHandler())(new AddMembershipCommand(self::ACTOR, self::ORGANISATION, self::USER));

        $handler = new ListOrganisationMembersHandler(
            new FakePermissionChecker(),
            $this->organisations,
            $this->memberships,
        );

        $members = ($handler)(new ListOrganisationMembersQuery(self::ACTOR, self::ORGANISATION));

        self::assertCount(1, $members);
        self::assertSame(self::USER, $members[0]['userId']);
        self::assertSame('active', $members[0]['status']);
    }

    private function addHandler(): AddMembershipHandler
    {
        return new AddMembershipHandler(
            new FakePermissionChecker(),
            $this->organisations,
            $this->memberships,
            $this->audit,
            new SequentialUuidGenerator(),
            $this->clock,
        );
    }

    private function seedOrganisation(): void
    {
        $this->organisations->save(Organisation::create(
            OrganisationId::fromString(self::ORGANISATION),
            'Acme',
            'acme',
            $this->clock->now(),
        ));
    }
}
