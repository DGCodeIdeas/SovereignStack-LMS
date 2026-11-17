<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Enrollment\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Enrollment\Domain\Entity\CourseCompletion;
use SovereignStack\Lms\Enrollment\Domain\ValueObject\CourseCompletionId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;

/**
 * @internal
 *
 * @coversNothing
 */
final class CourseCompletionTest extends TestCase
{
    private const ID = '01900000-0000-7000-8000-000000004101';
    private const ENROLLMENT_ID = '01900000-0000-7000-8000-000000004102';
    private const LEARNER_ID = '01900000-0000-7000-8000-000000004103';
    private const COURSE_ID = '01900000-0000-7000-8000-000000004104';
    private const COURSE_VERSION_ID = '01900000-0000-7000-8000-000000004105';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-16T11:00:00+00:00');
    }

    public function testRecordStoresCompletion(): void
    {
        $completion = $this->record();

        self::assertSame(self::ID, $completion->id()->value());
        self::assertSame(self::ENROLLMENT_ID, $completion->enrollmentId());
        self::assertSame(self::LEARNER_ID, $completion->learnerUserId()->value());
        self::assertSame(self::COURSE_ID, $completion->courseId());
        self::assertSame(self::COURSE_VERSION_ID, $completion->courseVersionId());
        self::assertEquals($this->now, $completion->completedAt());
        self::assertEquals($this->now, $completion->createdAt());
    }

    public function testRestorePreservesCreatedAt(): void
    {
        $createdAt = $this->now->modify('-1 minute');

        $completion = CourseCompletion::restore(
            CourseCompletionId::fromString(self::ID),
            self::ENROLLMENT_ID,
            UserIdReference::fromString(self::LEARNER_ID),
            self::COURSE_ID,
            self::COURSE_VERSION_ID,
            $this->now,
            $createdAt,
        );

        self::assertEquals($this->now, $completion->completedAt());
        self::assertEquals($createdAt, $completion->createdAt());
    }

    public function testRecordRejectsEmptyEnrollmentId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CourseCompletion::record(
            CourseCompletionId::fromString(self::ID),
            ' ',
            UserIdReference::fromString(self::LEARNER_ID),
            self::COURSE_ID,
            self::COURSE_VERSION_ID,
            $this->now,
        );
    }

    private function record(): CourseCompletion
    {
        return CourseCompletion::record(
            CourseCompletionId::fromString(self::ID),
            self::ENROLLMENT_ID,
            UserIdReference::fromString(self::LEARNER_ID),
            self::COURSE_ID,
            self::COURSE_VERSION_ID,
            $this->now,
        );
    }
}
