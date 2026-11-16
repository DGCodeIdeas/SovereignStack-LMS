<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\RemoveLessonCommand;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Course\Application\Support\CourseVersionResolver;
use SovereignStack\Lms\Course\Application\Support\Identifiers;
use SovereignStack\Lms\Course\Domain\Entity\CourseModule;
use SovereignStack\Lms\Course\Domain\Exception\CourseModuleNotFoundException;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotFoundException;
use SovereignStack\Lms\Course\Domain\Exception\LessonNotFoundException;
use SovereignStack\Lms\Course\Domain\Repository\CourseModuleRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\LessonRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseModuleId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;

final readonly class RemoveLessonHandler
{
    public function __construct(
        private CourseAccessPolicy $policy,
        private CourseVersionResolver $versionResolver,
        private CourseRepositoryInterface $courses,
        private CourseModuleRepositoryInterface $modules,
        private LessonRepositoryInterface $lessons,
        private AuditLoggerInterface $audit,
        private TransactionManagerInterface $transactions,
        private ClockInterface $clock,
    ) {}

    public function __invoke(RemoveLessonCommand $command): void
    {
        $courseId = Identifiers::courseId($command->courseId);
        $course = $this->courses->findById($courseId) ?? throw CourseNotFoundException::forId($command->courseId);

        $this->policy->requireEdit($command->actorUserId, $course);

        $version = $this->versionResolver->requireDraft($courseId);
        $module = $this->moduleInDraft($command->moduleId, $version->id());

        $lessonId = Identifiers::lessonId($command->lessonId);
        $lesson = $this->lessons->findById($lessonId) ?? throw LessonNotFoundException::forId($command->lessonId);

        if (!$lesson->courseModuleId()->equals($module->id())) {
            throw LessonNotFoundException::forId($command->lessonId);
        }

        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($module, $lessonId, $now): void {
            $this->lessons->delete($lessonId);
            $this->compactPositions($module->id(), $now);
        });

        $this->audit->record(
            'course.lesson_removed',
            $command->actorUserId,
            $course->organisationId()->value(),
            'lesson',
            $lessonId->value(),
            ['course_id' => $courseId->value(), 'module_id' => $module->id()->value()],
        );
    }

    private function moduleInDraft(string $moduleId, CourseVersionId $versionId): CourseModule
    {
        $module = $this->modules->findById(Identifiers::moduleId($moduleId))
            ?? throw CourseModuleNotFoundException::forId($moduleId);

        if (!$module->courseVersionId()->equals($versionId)) {
            throw CourseModuleNotFoundException::forId($moduleId);
        }

        return $module;
    }

    private function compactPositions(CourseModuleId $moduleId, \DateTimeImmutable $now): void
    {
        foreach ($this->lessons->listByModule($moduleId) as $index => $lesson) {
            if ($lesson->position() === $index) {
                continue;
            }

            $lesson->changePosition($index, $now);
            $this->lessons->save($lesson);
        }
    }
}
