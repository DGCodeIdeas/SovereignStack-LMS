<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\AssignInstructorCommand;
use SovereignStack\Lms\Course\Application\Handler\AssignInstructorHandler;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use SovereignStack\Lms\Tests\Unit\Course\Application\CourseApplicationTestCase;
use SovereignStack\Lms\Tests\Unit\Course\Application\Fakes\FakeCoursePermissionChecker;

/**
 * @internal
 *
 * @coversNothing
 */
final class AssignInstructorHandlerTest extends CourseApplicationTestCase
{
    public function testAssignsInstructorWithValidityWindow(): void
    {
        [$course] = $this->draftCourse();

        $instructorId = $this->handler()(new AssignInstructorCommand(
            self::ACTOR_ID,
            $course->id()->value(),
            self::INSTRUCTOR_ID,
            '2026-12-01T00:00:00+00:00',
            '2027-01-01T00:00:00+00:00',
        ));

        $assignment = $this->instructors->findByCourseAndUser($course->id(), UserIdReference::fromString(self::INSTRUCTOR_ID));
        self::assertNotNull($assignment);
        self::assertSame($instructorId->value(), $assignment->id()->value());
        self::assertSame(self::ACTOR_ID, $assignment->assignedBy()->value());
        self::assertSame('2026-12-01T00:00:00+00:00', $assignment->effectiveFrom()->format(\DATE_ATOM));
        self::assertSame('2027-01-01T00:00:00+00:00', $assignment->effectiveUntil()?->format(\DATE_ATOM));
        self::assertTrue($this->audit->hasEvent('course.instructor_assigned'));
    }

    public function testRejectsActorWithoutAdminPermission(): void
    {
        [$course] = $this->draftCourse();
        $this->permissions = new FakeCoursePermissionChecker(['user.manage']);
        $this->policy = new CourseAccessPolicy($this->permissions, $this->instructors);

        $this->expectException(ForbiddenException::class);

        $this->handler()(new AssignInstructorCommand(
            self::ACTOR_ID,
            $course->id()->value(),
            self::INSTRUCTOR_ID,
            '2026-12-01T00:00:00+00:00',
        ));
    }

    private function handler(): AssignInstructorHandler
    {
        return new AssignInstructorHandler(
            $this->policy,
            $this->courses,
            $this->instructors,
            $this->audit,
            $this->uuid,
            $this->clock,
        );
    }
}
