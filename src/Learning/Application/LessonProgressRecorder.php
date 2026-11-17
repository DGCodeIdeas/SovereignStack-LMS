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

    public function recordLessonProgress(
        string $actorUserId,
        string $enrollmentId,
        string $lessonId,
        string $target,
        string $organisationId,
        string $moduleId,
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

        $this->refreshModuleProgress($enrollmentId, $moduleId, $now);
    }

    private function refreshModuleProgress(string $enrollmentId, string $moduleId, \DateTimeImmutable $now): void
    {
        $lessons = $this->lessons->listByEnrollmentAndModule($enrollmentId, $moduleId);

        $module = $this->modules->findByEnrollmentAndModule($enrollmentId, $moduleId);

        if (null === $module) {
            if ([] === $lessons) {
                return;
            }

            $module = ModuleProgress::create(
                ModuleProgressId::fromString($this->uuid->generate()),
                $enrollmentId,
                $moduleId,
                $now,
            );

            if (self::allCompleted($lessons)) {
                $module->markCompleted($now);
            } elseif (self::anyStarted($lessons)) {
                $module->markStarted($now);
            } else {
                return;
            }

            $this->modules->save($module);

            return;
        }

        if (self::allCompleted($lessons)) {
            if (ProgressStatus::COMPLETED === $module->status()) {
                return;
            }

            $module->markCompleted($now);
        } elseif (self::anyStarted($lessons) && ProgressStatus::NOT_STARTED === $module->status()) {
            $module->markStarted($now);
        } else {
            return;
        }

        $this->modules->save($module);
    }

    /**
     * @param list<LessonProgress> $lessons
     */
    private static function allCompleted(array $lessons): bool
    {
        if ([] === $lessons) {
            return false;
        }

        foreach ($lessons as $lesson) {
            if (ProgressStatus::COMPLETED !== $lesson->status()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<LessonProgress> $lessons
     */
    private static function anyStarted(array $lessons): bool
    {
        foreach ($lessons as $lesson) {
            if (ProgressStatus::IN_PROGRESS === $lesson->status() || ProgressStatus::COMPLETED === $lesson->status()) {
                return true;
            }
        }

        return false;
    }
}
