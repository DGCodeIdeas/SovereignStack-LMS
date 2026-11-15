<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Handler\GetCourseHandler;
use SovereignStack\Lms\Course\Application\Handler\ListCoursesHandler;
use SovereignStack\Lms\Course\Application\Query\GetCourseQuery;
use SovereignStack\Lms\Course\Application\Query\ListCoursesQuery;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Tests\Unit\Course\Application\CourseApplicationTestCase;
use SovereignStack\Lms\Tests\Unit\Course\Application\Fakes\FakeCoursePermissionChecker;

/**
 * @internal
 *
 * @coversNothing
 */
final class CourseQueryHandlersTest extends CourseApplicationTestCase
{
    public function testListsCoursesForOrganisation(): void
    {
        $this->draftCourse('first');
        $this->draftCourse('second', '01900000-0000-7000-8000-000000000021');

        $rows = $this->listHandler()(new ListCoursesQuery(self::OWNER_ID, self::ORGANISATION_ID));

        self::assertCount(2, $rows);
        self::assertSame(['id', 'slug', 'currentPublishedVersionId', 'visibility', 'updatedAt'], array_keys($rows[0]));
        self::assertSame('first', $rows[0]['slug']);
        self::assertSame('second', $rows[1]['slug']);
    }

    public function testListingRequiresScopedPermission(): void
    {
        $this->draftCourse();
        $permissions = new FakeCoursePermissionChecker(['course.update', 'user.manage']);
        $this->policy = new CourseAccessPolicy($permissions, $this->instructors);

        $this->expectException(ForbiddenException::class);

        $this->listHandler()(new ListCoursesQuery(self::ACTOR_ID, self::ORGANISATION_ID));
    }

    public function testReturnsCourseContentForEditing(): void
    {
        [$course, $version] = $this->draftCourse();
        $module = $this->addModule($version, 'Module one');
        $lesson = $this->addLesson($module, 'Lesson one');
        $this->addContent($lesson, '<p>Body</p>');

        $payload = $this->getHandler()(new GetCourseQuery(self::OWNER_ID, $course->id()->value()));

        self::assertSame($course->id()->value(), $payload['id']);
        self::assertSame('intro', $payload['slug']);
        self::assertNotNull($payload['version']);
        self::assertSame($version->id()->value(), $payload['version']['id']);
        self::assertCount(1, $payload['version']['modules']);
        self::assertSame('Module one', $payload['version']['modules'][0]['title']);
        self::assertCount(1, $payload['version']['modules'][0]['lessons']);
        self::assertSame(
            ['html' => '<p>Body</p>', 'metadata' => ['format' => 'html']],
            $payload['version']['modules'][0]['lessons'][0]['content'],
        );
    }

    public function testGettingCourseRequiresViewAccess(): void
    {
        [$course] = $this->draftCourse();
        $permissions = new FakeCoursePermissionChecker(['user.manage', 'course.view']);
        $this->policy = new CourseAccessPolicy($permissions, $this->instructors);

        $this->expectException(ForbiddenException::class);

        $this->getHandler()(new GetCourseQuery(self::ACTOR_ID, $course->id()->value()));
    }

    private function listHandler(): ListCoursesHandler
    {
        return new ListCoursesHandler($this->policy, $this->courses);
    }

    private function getHandler(): GetCourseHandler
    {
        return new GetCourseHandler(
            $this->policy,
            $this->versionResolver,
            $this->courses,
            $this->versions,
            $this->modules,
            $this->lessons,
            $this->contents,
        );
    }
}
