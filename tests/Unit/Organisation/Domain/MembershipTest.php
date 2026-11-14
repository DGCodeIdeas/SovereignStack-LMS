<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Organisation\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Organisation\Domain\Entity\Membership;
use SovereignStack\Lms\Organisation\Domain\Enum\MembershipStatus;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;

/**
 * @internal
 *
 * @coversNothing
 */
final class MembershipTest extends TestCase
{
    private const MEMBERSHIP_ID = '01900000-0000-7000-8000-000000002000';
    private const ORGANISATION_ID = '01900000-0000-7000-8000-000000001000';
    private const USER_ID = '01900000-0000-7000-8000-000000000010';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-14T10:00:00+00:00');
    }

    public function testJoinCreatesActiveMembership(): void
    {
        $membership = $this->join();

        self::assertSame(self::MEMBERSHIP_ID, $membership->id());
        self::assertSame(self::ORGANISATION_ID, $membership->organisationId()->value());
        self::assertSame(self::USER_ID, $membership->userId());
        self::assertSame(MembershipStatus::ACTIVE, $membership->status());
        self::assertEquals($this->now, $membership->joinedAt());
        self::assertNull($membership->endedAt());
        self::assertEquals($this->now, $membership->createdAt());
    }

    public function testEndSetsStatusAndEndedAtIdempotently(): void
    {
        $membership = $this->join();
        $endedAt = $this->now->modify('+30 days');

        $membership->end($endedAt);
        $membership->end($this->now->modify('+60 days'));

        self::assertSame(MembershipStatus::ENDED, $membership->status());
        self::assertEquals($endedAt, $membership->endedAt());
    }

    private function join(): Membership
    {
        return Membership::join(
            self::MEMBERSHIP_ID,
            OrganisationId::fromString(self::ORGANISATION_ID),
            UserId::fromString(self::USER_ID),
            $this->now,
        );
    }
}
