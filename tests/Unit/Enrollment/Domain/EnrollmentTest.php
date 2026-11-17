<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Enrollment\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Enrollment\Domain\Entity\Enrollment;
use SovereignStack\Lms\Enrollment\Domain\Enum\EnrollmentStatus;
use SovereignStack\Lms\Enrollment\Domain\Exception\InvalidEnrollmentTransitionException;
use SovereignStack\Lms\Enrollment\Domain\ValueObject\EnrollmentId;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;

/**
 * @internal
 *
 * @coversNothing
 */
final class EnrollmentTest extends TestCase
{
    private const ID = '01900000-0000-7000-8000-000000004001';
    private const ORGANISATION_ID = '01900000-0000-7000-8000-000000004002';
    private const LEARNER_ID = '01900000-0000-7000-8000-000000004003';
    private const COURSE_ID = '01900000-0000-7000-8000-000000004004';
    private const COURSE_VERSION_ID = '01900000-0000-7000-8000-000000004005';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-16T10:00:00+00:00');
    }

    public function testCreateStartsActive(): void
    {
        $enrollment = $this->create();

        self::assertSame(self::ID, $enrollment->id()->value());
        self::assertSame(self::ORGANISATION_ID, $enrollment->organisationId()->value());
        self::assertSame(self::LEARNER_ID, $enrollment->learnerUserId()->value());
        self::assertSame(self::COURSE_ID, $enrollment->courseId());
        self::assertSame(self::COURSE_VERSION_ID, $enrollment->courseVersionId());
        self::assertSame(EnrollmentStatus::ACTIVE, $enrollment->status());
        self::assertEquals($this->now, $enrollment->enrolledAt());
        self::assertNull($enrollment->completedAt());
        self::assertNull($enrollment->cancelledAt());
        self::assertEquals($this->now, $enrollment->createdAt());
        self::assertEquals($this->now, $enrollment->updatedAt());
    }

    public function testCompleteMovesActiveToCompleted(): void
    {
        $enrollment = $this->create();
        $completedAt = $this->now->modify('+1 hour');

        $enrollment->complete($completedAt);

        self::assertSame(EnrollmentStatus::COMPLETED, $enrollment->status());
        self::assertEquals($completedAt, $enrollment->completedAt());
        self::assertNull($enrollment->cancelledAt());
        self::assertEquals($completedAt, $enrollment->updatedAt());
    }

    public function testCancelMovesActiveToCancelled(): void
    {
        $enrollment = $this->create();
        $cancelledAt = $this->now->modify('+2 hours');

        $enrollment->cancel($cancelledAt);

        self::assertSame(EnrollmentStatus::CANCELLED, $enrollment->status());
        self::assertNull($enrollment->completedAt());
        self::assertEquals($cancelledAt, $enrollment->cancelledAt());
        self::assertEquals($cancelledAt, $enrollment->updatedAt());
    }

    public function testCompleteFromCancelledIsRejected(): void
    {
        $enrollment = $this->create();
        $enrollment->cancel($this->now->modify('+1 hour'));

        try {
            $enrollment->complete($this->now->modify('+2 hours'));
            self::fail('Completing a cancelled enrollment should be rejected.');
        } catch (InvalidEnrollmentTransitionException $exception) {
            self::assertSame('cancelled', $exception->currentStatus());
            self::assertSame('completed', $exception->attemptedStatus());
            self::assertSame(['active'], $exception->allowedStatuses());
        }
    }

    public function testCancelFromCompletedIsRejected(): void
    {
        $enrollment = $this->create();
        $enrollment->complete($this->now->modify('+1 hour'));

        $this->expectException(InvalidEnrollmentTransitionException::class);

        $enrollment->cancel($this->now->modify('+2 hours'));
    }

    public function testAssertActiveAcceptsActiveAndRejectsOtherStates(): void
    {
        $enrollment = $this->create();
        $enrollment->assertActive();

        $enrollment->complete($this->now->modify('+1 hour'));

        $this->expectException(InvalidEnrollmentTransitionException::class);

        $enrollment->assertActive();
    }

    public function testRestorePreservesPersistedState(): void
    {
        $enrolledAt = $this->now->modify('-1 day');
        $completedAt = $this->now->modify('-1 hour');
        $createdAt = $this->now->modify('-2 days');
        $updatedAt = $this->now;

        $enrollment = Enrollment::restore(
            EnrollmentId::fromString(self::ID),
            OrganisationIdReference::fromString(self::ORGANISATION_ID),
            UserIdReference::fromString(self::LEARNER_ID),
            self::COURSE_ID,
            self::COURSE_VERSION_ID,
            EnrollmentStatus::COMPLETED,
            $enrolledAt,
            $completedAt,
            null,
            $createdAt,
            $updatedAt,
        );

        self::assertSame(EnrollmentStatus::COMPLETED, $enrollment->status());
        self::assertEquals($enrolledAt, $enrollment->enrolledAt());
        self::assertEquals($completedAt, $enrollment->completedAt());
        self::assertNull($enrollment->cancelledAt());
        self::assertEquals($createdAt, $enrollment->createdAt());
        self::assertEquals($updatedAt, $enrollment->updatedAt());
    }

    public function testCreateRejectsEmptyCourseId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Enrollment::create(
            EnrollmentId::fromString(self::ID),
            OrganisationIdReference::fromString(self::ORGANISATION_ID),
            UserIdReference::fromString(self::LEARNER_ID),
            ' ',
            self::COURSE_VERSION_ID,
            $this->now,
        );
    }

    public function testCreateRejectsEmptyCourseVersionId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Enrollment::create(
            EnrollmentId::fromString(self::ID),
            OrganisationIdReference::fromString(self::ORGANISATION_ID),
            UserIdReference::fromString(self::LEARNER_ID),
            self::COURSE_ID,
            '',
            $this->now,
        );
    }

    private function create(): Enrollment
    {
        return Enrollment::create(
            EnrollmentId::fromString(self::ID),
            OrganisationIdReference::fromString(self::ORGANISATION_ID),
            UserIdReference::fromString(self::LEARNER_ID),
            self::COURSE_ID,
            self::COURSE_VERSION_ID,
            $this->now,
        );
    }
}
