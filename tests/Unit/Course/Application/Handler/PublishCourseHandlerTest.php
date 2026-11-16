<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\PublishCourseCommand;
use SovereignStack\Lms\Course\Application\Handler\PublishCourseHandler;
use SovereignStack\Lms\Course\Domain\Enum\CourseVersionStatus;
use SovereignStack\Lms\Course\Domain\Exception\InvalidCourseTransitionException;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Tests\Unit\Course\Application\CourseApplicationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class PublishCourseHandlerTest extends CourseApplicationTestCase
{
    public function testPublishesReviewVersionAndLinksCourse(): void
    {
        [$course, $version] = $this->draftCourse();
        $module = $this->addModule($version);
        $lesson = $this->addLesson($module);
        $this->addContent($lesson, '<p>Ready</p>');
        $version->submitForReview();
        $this->versions->save($version);

        $this->handler()(new PublishCourseCommand(self::OWNER_ID, $course->id()->value()));

        self::assertSame(CourseVersionStatus::PUBLISHED, $version->status());
        self::assertNotNull($version->publishedAt());
        self::assertSame($version->id()->value(), $course->currentPublishedVersionId());
        self::assertSame($course->id()->value(), $course->currentPublishedVersionCourseId());
        self::assertSame(1, $this->courses->publishedVersionUpdates);

        $record = $this->audit->records[0];
        self::assertSame('course.published', $record['eventType']);
        self::assertSame($version->id()->value(), $record['data']['version_id']);
        self::assertSame(1, $record['data']['version_number']);
    }

    public function testRejectsRequiredModuleWithoutContent(): void
    {
        [$course, $version] = $this->draftCourse();
        $module = $this->addModule($version);
        $this->addLesson($module);
        $version->submitForReview();
        $this->versions->save($version);

        try {
            $this->handler()(new PublishCourseCommand(self::OWNER_ID, $course->id()->value()));
            self::fail('A required module without content must block publishing.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('modules', $exception->errors());
            self::assertSame(CourseVersionStatus::REVIEW, $version->status());
            self::assertSame(0, $this->courses->publishedVersionUpdates);
        }
    }

    public function testRejectsRepublishingPublishedVersion(): void
    {
        [$course] = $this->publishedCourse();

        $this->expectException(InvalidCourseTransitionException::class);

        $this->handler()(new PublishCourseCommand(self::OWNER_ID, $course->id()->value()));
    }

    private function handler(): PublishCourseHandler
    {
        return new PublishCourseHandler(
            $this->policy,
            $this->versionResolver,
            $this->courses,
            $this->versions,
            $this->modules,
            $this->lessons,
            $this->contents,
            $this->audit,
            $this->transactions,
            $this->clock,
        );
    }
}
