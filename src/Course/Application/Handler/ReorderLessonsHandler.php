<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\ReorderLessonsCommand;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Course\Application\Support\CourseVersionResolver;
use SovereignStack\Lms\Course\Application\Support\Identifiers;
use SovereignStack\Lms\Course\Domain\Entity\CourseModule;
use SovereignStack\Lms\Course\Domain\Entity\Lesson;
use SovereignStack\Lms\Course\Domain\Exception\CourseModuleNotFoundException;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotFoundException;
use SovereignStack\Lms\Course\Domain\Repository\CourseModuleRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\LessonRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;
use SovereignStack\Lms\Course\Domain\ValueObject\LessonId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;

final readonly class ReorderLessonsHandler
{
    public function __construct(
        private CourseAccessPolicy $policy,
        private CourseVersionResolver $versionResolver,
        private CourseRepositoryInterface $courses,
        private CourseModuleRepositoryInterface $modules,
        private LessonRepositoryInterface $lessons,
        private AuditLoggerInterface $audit,
        private TransactionManagerInterface $transactions,
    ) {}

    /**
     * @return list<string>
     */
    public function __invoke(ReorderLessonsCommand $command): array
    {
        $courseId = Identifiers::courseId($command->courseId);
        $course = $this->courses->findById($courseId) ?? throw CourseNotFoundException::forId($command->courseId);

        $this->policy->requireEdit($command->actorUserId, $course);

        $version = $this->versionResolver->requireDraft($courseId);
        $module = $this->moduleInDraft($command->moduleId, $version->id());

        $lessonIds = array_map(
            static fn (string $id): LessonId => Identifiers::lessonId($id),
            array_values($command->lessonIds),
        );

        $stored = $this->lessons->listByModule($module->id());
        $this->assertExactSet($stored, $lessonIds);

        $this->transactions->transactional(function () use ($module, $lessonIds): void {
            $this->lessons->reorder($module->id(), $lessonIds);
        });

        $ordered = array_map(static fn (LessonId $id): string => $id->value(), $lessonIds);

        $this->audit->record(
            'course.lessons_reordered',
            $command->actorUserId,
            $course->organisationId()->value(),
            'lesson',
            $module->id()->value(),
            ['course_id' => $courseId->value(), 'lesson_ids' => implode(',', $ordered)],
        );

        return $ordered;
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

    /**
     * @param list<Lesson>   $stored
     * @param list<LessonId> $requested
     */
    private function assertExactSet(array $stored, array $requested): void
    {
        $storedIds = array_map(static fn (Lesson $lesson): string => $lesson->id()->value(), $stored);
        $requestedIds = array_map(static fn (LessonId $id): string => $id->value(), $requested);

        $expected = $storedIds;
        sort($expected);
        $actual = $requestedIds;
        sort($actual);

        if ($expected !== $actual || \count($requestedIds) !== \count(array_unique($requestedIds))) {
            throw ValidationException::withErrors([
                'lessonIds' => ['The reorder list must contain exactly the current lessons of the module.'],
            ]);
        }
    }
}
