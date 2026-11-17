<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\CreateCourseCommand;
use SovereignStack\Lms\Course\Application\Handler\CreateCourseHandler;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Course\Domain\Enum\CourseVersionStatus;
use SovereignStack\Lms\Course\Domain\Enum\CourseVisibility;
use SovereignStack\Lms\Course\Domain\Exception\DuplicateCourseSlugException;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Tests\Unit\Course\Application\CourseApplicationTestCase;
use SovereignStack\Lms\Tests\Unit\Course\Application\Fakes\FakeCoursePermissionChecker;

/**
 * @internal
 *
 * @coversNothing
 */
final class CreateCourseHandlerTest extends CourseApplicationTestCase
{
    public function testCreatesCourseWithInitialDraftVersion(): void
    {
        $handler = $this->handler();

        $courseId = $handler(new CreateCourseCommand(
            self::OWNER_ID,
            self::ORGANISATION_ID,
            '  Introduction to LMS  ',
            '  The first course  ',
            '  Intro-To-LMS ',
        ));

        $course = $this->courses->findById($courseId);
        self::assertNotNull($course);
        self::assertSame('intro-to-lms', $course->slug());
        self::assertSame(CourseVisibility::ORGANISATION_ONLY, $course->visibility());
        self::assertSame(self::OWNER_ID, $course->ownerUserId()->value());

        $version = $this->versions->findDraftByCourse($courseId);
        self::assertNotNull($version);
        self::assertSame(1, $version->versionNumber());
        self::assertSame('Introduction to LMS', $version->title());
        self::assertSame('The first course', $version->description());
        self::assertSame(CourseVersionStatus::DRAFT, $version->status());

        self::assertSame(1, $this->transactions->calls);
        self::assertTrue($this->audit->hasEvent('course.created'));
    }

    public function testRejectsDuplicateSlugInSameOrganisation(): void
    {
        $this->draftCourse('intro');
        $handler = $this->handler();

        $this->expectException(DuplicateCourseSlugException::class);

        $handler(new CreateCourseCommand(self::OWNER_ID, self::ORGANISATION_ID, 'Another', '', 'intro'));
    }

    public function testRejectsWhenActorCannotCreateCourses(): void
    {
        $this->permissions = new FakeCoursePermissionChecker(['course.create']);
        $this->policy = new CourseAccessPolicy($this->permissions, $this->instructors, $this->clock);

        $this->expectException(ForbiddenException::class);

        $this->handler()(new CreateCourseCommand(self::ACTOR_ID, self::ORGANISATION_ID, 'Course', '', 'course'));
    }

    private function handler(): CreateCourseHandler
    {
        return new CreateCourseHandler(
            $this->policy,
            $this->courses,
            $this->versions,
            $this->audit,
            $this->transactions,
            $this->uuid,
            $this->clock,
        );
    }
}
