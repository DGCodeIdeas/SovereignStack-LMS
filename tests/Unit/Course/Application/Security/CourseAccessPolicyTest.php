<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Security;

use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Course\Domain\Entity\CourseInstructor;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseInstructorId;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use SovereignStack\Lms\Tests\Unit\Course\Application\CourseApplicationTestCase;
use SovereignStack\Lms\Tests\Unit\Course\Application\Fakes\FakeCoursePermissionChecker;

/**
 * @internal
 *
 * @coversNothing
 */
final class CourseAccessPolicyTest extends CourseApplicationTestCase
{
    public function testOwnerMayEditWithoutPermissions(): void
    {
        [$course] = $this->draftCourse();
        $policy = new CourseAccessPolicy(new FakeCoursePermissionChecker(['user.manage', 'course.view']), $this->instructors, $this->clock);

        $policy->requireEdit(self::OWNER_ID, $course);

        self::addToAssertionCount(1);
    }

    public function testAssignedInstructorMayEdit(): void
    {
        [$course] = $this->draftCourse();
        $this->assignInstructor($course->id());
        $policy = new CourseAccessPolicy(
            new FakeCoursePermissionChecker(['user.manage', 'course.view']),
            $this->instructors,
            $this->clock,
        );

        $policy->requireEdit(self::INSTRUCTOR_ID, $course);

        self::addToAssertionCount(1);
    }

    public function testExpiredInstructorAssignmentDoesNotAuthorizeEditing(): void
    {
        [$course] = $this->draftCourse();
        $this->assignInstructor(
            $course->id(),
            $this->clock->now()->modify('-10 days'),
            $this->clock->now()->modify('-1 day'),
        );
        $policy = new CourseAccessPolicy(
            new FakeCoursePermissionChecker(['user.manage', 'course.view']),
            $this->instructors,
            $this->clock,
        );

        $this->expectException(ForbiddenException::class);

        $policy->requireEdit(self::INSTRUCTOR_ID, $course);
    }

    public function testFutureInstructorAssignmentDoesNotAuthorizeEditing(): void
    {
        [$course] = $this->draftCourse();
        $this->assignInstructor(
            $course->id(),
            $this->clock->now()->modify('+1 day'),
            null,
        );
        $policy = new CourseAccessPolicy(
            new FakeCoursePermissionChecker(['user.manage', 'course.view']),
            $this->instructors,
            $this->clock,
        );

        $this->expectException(ForbiddenException::class);

        $policy->requireEdit(self::INSTRUCTOR_ID, $course);
    }

    public function testAdministratorMayEditThroughPermission(): void
    {
        [$course] = $this->draftCourse();

        $this->policy->requireEdit(self::ACTOR_ID, $course);

        self::addToAssertionCount(1);
    }

    public function testUnrelatedUserIsDeniedEditing(): void
    {
        [$course] = $this->draftCourse();
        $policy = new CourseAccessPolicy(new FakeCoursePermissionChecker(['user.manage']), $this->instructors, $this->clock);

        $this->expectException(ForbiddenException::class);

        $policy->requireEdit(self::ACTOR_ID, $course);
    }

    public function testAdminOperationsRequireUserManagePermission(): void
    {
        [$course] = $this->draftCourse();
        $policy = new CourseAccessPolicy(new FakeCoursePermissionChecker(['user.manage']), $this->instructors, $this->clock);

        $this->expectException(ForbiddenException::class);

        $policy->requireAdmin(self::ACTOR_ID, $course);
    }

    public function testCreateRequiresCourseCreatePermission(): void
    {
        $policy = new CourseAccessPolicy(new FakeCoursePermissionChecker(['course.create']), $this->instructors, $this->clock);

        $this->expectException(ForbiddenException::class);

        $policy->requireCreate(self::ACTOR_ID, self::ORGANISATION_ID);
    }

    private function assignInstructor(
        CourseId $courseId,
        ?\DateTimeImmutable $effectiveFrom = null,
        ?\DateTimeImmutable $effectiveUntil = null,
    ): void {
        $this->instructors->save(CourseInstructor::assign(
            CourseInstructorId::fromString($this->uuid->generate()),
            $courseId,
            UserIdReference::fromString(self::INSTRUCTOR_ID),
            UserIdReference::fromString(self::OWNER_ID),
            $this->clock->now(),
            $effectiveFrom ?? $this->clock->now(),
            $effectiveUntil,
        ));
    }
}
