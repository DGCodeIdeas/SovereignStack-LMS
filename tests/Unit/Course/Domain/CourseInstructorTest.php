<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Course\Domain\Entity\CourseInstructor;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseInstructorId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;

/**
 * @internal
 *
 * @coversNothing
 */
final class CourseInstructorTest extends TestCase
{
    private const ID = '01900000-0000-7000-8000-000000003501';
    private const COURSE_ID = '01900000-0000-7000-8000-000000003502';
    private const USER_ID = '01900000-0000-7000-8000-000000003503';
    private const ADMIN_ID = '01900000-0000-7000-8000-000000003504';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-15T10:00:00+00:00');
    }

    public function testAssignStoresAssignment(): void
    {
        $instructor = $this->assign($this->now, $this->now->modify('+30 days'));

        self::assertSame(self::ID, $instructor->id()->value());
        self::assertSame(self::COURSE_ID, $instructor->courseId()->value());
        self::assertSame(self::USER_ID, $instructor->userId()->value());
        self::assertSame(self::ADMIN_ID, $instructor->assignedBy()->value());
        self::assertEquals($this->now, $instructor->assignedAt());
        self::assertEquals($this->now, $instructor->effectiveFrom());
        self::assertEquals($this->now->modify('+30 days'), $instructor->effectiveUntil());
    }

    public function testIsEffectiveAtRespectsWindow(): void
    {
        $instructor = $this->assign($this->now, $this->now->modify('+10 days'));

        self::assertFalse($instructor->isEffectiveAt($this->now->modify('-1 second')));
        self::assertTrue($instructor->isEffectiveAt($this->now));
        self::assertTrue($instructor->isEffectiveAt($this->now->modify('+5 days')));
        self::assertFalse($instructor->isEffectiveAt($this->now->modify('+10 days')));
    }

    public function testOpenEndedAssignmentStaysEffective(): void
    {
        $instructor = $this->assign($this->now, null);

        self::assertNull($instructor->effectiveUntil());
        self::assertTrue($instructor->isEffectiveAt($this->now->modify('+365 days')));
    }

    public function testRejectsWindowThatEndsBeforeItStarts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->assign($this->now, $this->now);
    }

    private function assign(\DateTimeImmutable $effectiveFrom, ?\DateTimeImmutable $effectiveUntil): CourseInstructor
    {
        return CourseInstructor::assign(
            CourseInstructorId::fromString(self::ID),
            CourseId::fromString(self::COURSE_ID),
            UserIdReference::fromString(self::USER_ID),
            UserIdReference::fromString(self::ADMIN_ID),
            $this->now,
            $effectiveFrom,
            $effectiveUntil,
        );
    }
}
