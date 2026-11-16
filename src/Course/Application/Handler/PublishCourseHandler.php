<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\PublishCourseCommand;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Course\Application\Support\CourseVersionResolver;
use SovereignStack\Lms\Course\Application\Support\Identifiers;
use SovereignStack\Lms\Course\Domain\Entity\CourseModule;
use SovereignStack\Lms\Course\Domain\Entity\Lesson;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotFoundException;
use SovereignStack\Lms\Course\Domain\Exception\CourseVersionNotFoundException;
use SovereignStack\Lms\Course\Domain\Repository\CourseModuleRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseVersionRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\LessonContentRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\LessonRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;

final readonly class PublishCourseHandler
{
    public function __construct(
        private CourseAccessPolicy $policy,
        private CourseVersionResolver $versionResolver,
        private CourseRepositoryInterface $courses,
        private CourseVersionRepositoryInterface $versions,
        private CourseModuleRepositoryInterface $modules,
        private LessonRepositoryInterface $lessons,
        private LessonContentRepositoryInterface $contents,
        private AuditLoggerInterface $audit,
        private TransactionManagerInterface $transactions,
        private ClockInterface $clock,
    ) {}

    public function __invoke(PublishCourseCommand $command): void
    {
        $courseId = Identifiers::courseId($command->courseId);
        $course = $this->courses->findById($courseId) ?? throw CourseNotFoundException::forId($command->courseId);

        $this->policy->requireAdmin($command->actorUserId, $course);

        $version = $this->versionResolver->latest($courseId) ?? throw CourseVersionNotFoundException::draftNotFound($command->courseId);

        $this->assertRequiredContent($version->id());

        $now = $this->clock->now();
        $version->publish($now);

        $this->transactions->transactional(function () use ($course, $version, $now): void {
            $this->versions->save($version);
            $course->publishVersion($version->id()->value(), $now);
            $this->courses->updatePublishedVersion($course);
        });

        $this->audit->record(
            'course.published',
            $command->actorUserId,
            $course->organisationId()->value(),
            'course',
            $courseId->value(),
            [
                'version_id' => $version->id()->value(),
                'version_number' => $version->versionNumber(),
            ],
        );
    }

    private function assertRequiredContent(CourseVersionId $versionId): void
    {
        $errors = [];

        foreach ($this->modules->listByVersion($versionId) as $module) {
            if (!$module->isRequired()) {
                continue;
            }

            if (!$this->moduleHasContent($module)) {
                $errors[] = sprintf('Required module "%s" must have at least one lesson with content.', $module->id()->value());
            }
        }

        if ([] !== $errors) {
            throw ValidationException::withErrors(['modules' => $errors]);
        }
    }

    private function moduleHasContent(CourseModule $module): bool
    {
        foreach ($this->lessons->listByModule($module->id()) as $lesson) {
            if ($this->lessonHasContent($lesson)) {
                return true;
            }
        }

        return false;
    }

    private function lessonHasContent(Lesson $lesson): bool
    {
        $content = $this->contents->findByLesson($lesson->id());

        return null !== $content && '' !== trim($content->contentHtml());
    }
}
