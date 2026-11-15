<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\SubmitForReviewCommand;
use SovereignStack\Lms\Course\Application\Handler\SubmitForReviewHandler;
use SovereignStack\Lms\Course\Domain\Entity\CourseModule;
use SovereignStack\Lms\Course\Domain\Enum\CourseVersionStatus;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Tests\Unit\Course\Application\CourseApplicationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class SubmitForReviewHandlerTest extends CourseApplicationTestCase
{
    public function testRejectsCourseWithoutModules(): void
    {
        [$course, $version] = $this->draftCourse();

        try {
            $this->handler()(new SubmitForReviewCommand(self::OWNER_ID, $course->id()->value()));
            self::fail('A course without modules must not be submittable.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('modules', $exception->errors());
            self::assertSame(CourseVersionStatus::DRAFT, $version->status());
        }
    }

    public function testRejectsModuleWithoutTitle(): void
    {
        [$course, $version] = $this->draftCourse();
        $module = $this->addModule($version);
        (new \ReflectionProperty(CourseModule::class, 'title'))->setValue($module, '   ');

        $this->expectException(ValidationException::class);

        $this->handler()(new SubmitForReviewCommand(self::OWNER_ID, $course->id()->value()));
    }

    public function testSubmitsDraftWithModulesForReview(): void
    {
        [$course, $version] = $this->draftCourse();
        $module = $this->addModule($version);
        $this->addLesson($module);

        $this->handler()(new SubmitForReviewCommand(self::OWNER_ID, $course->id()->value()));

        self::assertSame(CourseVersionStatus::REVIEW, $version->status());
        self::assertTrue($this->audit->hasEvent('course.submitted_for_review'));
    }

    private function handler(): SubmitForReviewHandler
    {
        return new SubmitForReviewHandler(
            $this->policy,
            $this->versionResolver,
            $this->courses,
            $this->modules,
            $this->versions,
            $this->audit,
        );
    }
}
