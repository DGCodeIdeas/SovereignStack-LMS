<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\AddLessonCommand;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Course\Application\Support\CourseVersionResolver;
use SovereignStack\Lms\Course\Application\Support\Identifiers;
use SovereignStack\Lms\Course\Domain\Entity\CourseModule;
use SovereignStack\Lms\Course\Domain\Entity\Lesson;
use SovereignStack\Lms\Course\Domain\Exception\CourseModuleNotFoundException;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotFoundException;
use SovereignStack\Lms\Course\Domain\Exception\InvalidCourseContentException;
use SovereignStack\Lms\Course\Domain\Repository\CourseModuleRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\LessonRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;
use SovereignStack\Lms\Course\Domain\ValueObject\LessonId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;

final readonly class AddLessonHandler
{
    public function __construct(
        private CourseAccessPolicy $policy,
        private CourseVersionResolver $versionResolver,
        private CourseRepositoryInterface $courses,
        private CourseModuleRepositoryInterface $modules,
        private LessonRepositoryInterface $lessons,
        private AuditLoggerInterface $audit,
        private UuidGeneratorInterface $uuid,
        private ClockInterface $clock,
    ) {}

    public function __invoke(AddLessonCommand $command): LessonId
    {
        $courseId = Identifiers::courseId($command->courseId);
        $course = $this->courses->findById($courseId) ?? throw CourseNotFoundException::forId($command->courseId);

        $this->policy->requireEdit($command->actorUserId, $course);

        $version = $this->versionResolver->requireDraft($courseId);
        $module = $this->moduleInDraft($command->moduleId, $version->id());

        $lessonId = LessonId::fromString($this->uuid->generate());

        try {
            $lesson = Lesson::create(
                $lessonId,
                $module->id(),
                trim($command->title),
                $this->lessons->nextPosition($module->id()),
                $this->clock->now(),
            );
        } catch (InvalidCourseContentException $exception) {
            throw ValidationException::withErrors(['lesson' => [$exception->getMessage()]]);
        }

        $this->lessons->save($lesson);

        $this->audit->record(
            'course.lesson_added',
            $command->actorUserId,
            $course->organisationId()->value(),
            'lesson',
            $lessonId->value(),
            ['course_id' => $courseId->value(), 'module_id' => $module->id()->value(), 'position' => $lesson->position()],
        );

        return $lessonId;
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
}
