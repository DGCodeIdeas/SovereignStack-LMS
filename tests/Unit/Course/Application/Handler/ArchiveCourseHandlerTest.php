<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\ArchiveCourseCommand;
use SovereignStack\Lms\Course\Application\Handler\ArchiveCourseHandler;
use SovereignStack\Lms\Course\Domain\Enum\CourseVersionStatus;
use SovereignStack\Lms\Course\Domain\Exception\CourseVersionNotFoundException;
use SovereignStack\Lms\Tests\Unit\Course\Application\CourseApplicationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ArchiveCourseHandlerTest extends CourseApplicationTestCase
{
    public function testArchivesPublishedVersionAndClearsReferences(): void
    {
        [$course, $version] = $this->publishedCourse();

        $this->handler()(new ArchiveCourseCommand(self::ACTOR_ID, $course->id()->value()));

        self::assertSame(CourseVersionStatus::ARCHIVED, $version->status());
        self::assertNull($course->currentPublishedVersionId());
        self::assertNull($course->currentPublishedVersionCourseId());
        self::assertSame(1, $this->courses->publishedVersionClears);
        self::assertTrue($this->audit->hasEvent('course.archived'));
    }

    public function testRejectsCourseWithoutPublishedVersion(): void
    {
        [$course] = $this->draftCourse();

        $this->expectException(CourseVersionNotFoundException::class);

        $this->handler()(new ArchiveCourseCommand(self::ACTOR_ID, $course->id()->value()));
    }

    private function handler(): ArchiveCourseHandler
    {
        return new ArchiveCourseHandler(
            $this->policy,
            $this->courses,
            $this->versions,
            $this->audit,
            $this->transactions,
            $this->clock,
        );
    }
}
