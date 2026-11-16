<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\UpdateCourseCommand;
use SovereignStack\Lms\Course\Application\Handler\UpdateCourseHandler;
use SovereignStack\Lms\Course\Domain\Enum\CourseVisibility;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotEditableException;
use SovereignStack\Lms\Tests\Unit\Course\Application\CourseApplicationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class UpdateCourseHandlerTest extends CourseApplicationTestCase
{
    public function testUpdatesCourseAndDraftDetails(): void
    {
        [$course, $version] = $this->draftCourse();

        $this->handler()(new UpdateCourseCommand(
            self::OWNER_ID,
            $course->id()->value(),
            '  Renamed course ',
            '  New description ',
            ' Renamed-Slug ',
            'public',
        ));

        $updated = $this->courses->findById($course->id());
        self::assertNotNull($updated);
        self::assertSame('renamed-slug', $updated->slug());
        self::assertSame(CourseVisibility::PUBLIC, $updated->visibility());
        self::assertSame('Renamed course', $version->title());
        self::assertSame('New description', $version->description());
        self::assertTrue($this->audit->hasEvent('course.updated'));
    }

    public function testRejectsUpdateWhenNoDraftExists(): void
    {
        [$course] = $this->publishedCourse();

        try {
            $this->handler()(new UpdateCourseCommand(
                self::OWNER_ID,
                $course->id()->value(),
                'Changed',
                '',
                'changed',
                'public',
            ));
            self::fail('Updating a published course without a draft must be rejected.');
        } catch (CourseNotEditableException) {
            self::assertSame('intro', $course->slug());
            self::assertSame(CourseVisibility::ORGANISATION_ONLY, $course->visibility());
        }
    }

    private function handler(): UpdateCourseHandler
    {
        return new UpdateCourseHandler(
            $this->policy,
            $this->versionResolver,
            $this->courses,
            $this->versions,
            $this->audit,
            $this->transactions,
            $this->clock,
        );
    }
}
