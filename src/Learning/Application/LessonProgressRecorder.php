<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Application;

use SovereignStack\Lms\Learning\Application\Contract\LessonProgressRecorderInterface;
use SovereignStack\Lms\Learning\Domain\Entity\LessonProgress;
use SovereignStack\Lms\Learning\Domain\Entity\ModuleProgress;
use SovereignStack\Lms\Learning\Domain\Enum\ProgressStatus;
use SovereignStack\Lms\Learning\Domain\Repository\LessonProgressRepositoryInterface;
use SovereignStack\Lms\Learning\Domain\Repository\ModuleProgressRepositoryInterface;
use SovereignStack\Lms\Learning\Domain\ValueObject\LessonProgressId;
use SovereignStack\Lms\Learning\Domain\ValueObject\ModuleProgressId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;

final readonly class LessonProgressRecorder implements LessonProgressRecorderInterface
{
    public function __construct(
        private LessonProgressRepositoryInterface $lessons,
        private ModuleProgressRepositoryInterface $modules,
        private AuditLoggerInterface $audit,
        private UuidGeneratorInterface $uuid,
    ) {}

    /**
     * @param list<string> $moduleLessonIds
     */
    public function recordLessonProgress(
        string $actorUserId,
        string $enrollmentId,
        string $lessonId,
        string $target,
        string $organisationId,
        string $moduleId,
        array $moduleLessonIds,
        \DateTimeImmutable $now,
    ): void {
        $progress = $this->lessons->findByEnrollmentAndLesson($enrollmentId, $lessonId);

        if (null === $progress) {
            $progress = LessonProgress::create(
                LessonProgressId::fromString($this->uuid->generate()),
                $enrollmentId,
                $lessonId,
                $now,
            );
        }

        if ($progress->status()->value === $target) {
            return;
        }

        if (ProgressStatus::IN_PROGRESS->value === $target) {
            $progress->markStarted($now);
        } else {
            $progress->markCompleted($now);
        }

        $this->lessons->save($progress);

        $this->audit->record(
            'learning.lesson_progressed',
            $actorUserId,
            $organisationId,
            'lesson_progress',
            $progress->id()->value(),
            [
                'enrollment_id' => $enrollmentId,
                'lesson_id' => $lessonId,
                'status' => $progress->status()->value,
            ],
        );

        $this->refreshModuleProgress($enrollmentId, $moduleId, $moduleLessonIds, $now);
    }

    /**
     * @param list<string> $moduleLessonIds
     */
    private function refreshModuleProgress(
        string $enrollmentId,
        string $moduleId,
        array $moduleLessonIds,
        \DateTimeImmutable $now,
    ): void {
        // A module with no lessons can never be completed. It also has no
        // lesson progress to aggregate, so leave it without progress rows.
        if ([] === $moduleLessonIds) {
            return;
        }

        $recorded = [];

        foreach ($this->lessons->listByEnrollmentAndModule($enrollmentId, $moduleId) as $lesson) {
            $recorded[$lesson->lessonId()] = $lesson->status();
        }

        $allCompleted = true;
        $anyStarted = false;

        foreach ($moduleLessonIds as $moduleLessonId) {
            $status = $recorded[$moduleLessonId] ?? null;

            if (ProgressStatus::COMPLETED !== $status) {
                $allCompleted = false;
            }

            if (ProgressStatus::IN_PROGRESS === $status || ProgressStatus::COMPLETED === $status) {
                $anyStarted = true;
            }
        }

        $module = $this->modules->findByEnrollmentAndModule($enrollmentId, $moduleId);

        if ($allCompleted) {
            if (null === $module) {
                $module = ModuleProgress::create(
                    ModuleProgressId::fromString($this->uuid->generate()),
                    $enrollmentId,
                    $moduleId,
                    $now,
                );
            } elseif (ProgressStatus::COMPLETED === $module->status()) {
                return;
            }

            $module->markCompleted($now);
            $this->modules->save($module);

            return;
        }

        if (!$anyStarted) {
            return;
        }

        if (null === $module) {
            $module = ModuleProgress::create(
                ModuleProgressId::fromString($this->uuid->generate()),
                $enrollmentId,
                $moduleId,
                $now,
            );
            $module->markStarted($now);
            $this->modules->save($module);

            return;
        }

        if (ProgressStatus::NOT_STARTED !== $module->status()) {
            return;
        }

        $module->markStarted($now);
        $this->modules->save($module);
    }
}
