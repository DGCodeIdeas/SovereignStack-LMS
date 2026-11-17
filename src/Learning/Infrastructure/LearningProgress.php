<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Infrastructure;

use SovereignStack\Lms\Learning\Application\Contract\LearningProgressInterface;
use SovereignStack\Lms\Learning\Application\Contract\RequiredModulesInterface;
use SovereignStack\Lms\Learning\Domain\Enum\ProgressStatus;
use SovereignStack\Lms\Learning\Domain\Repository\LessonProgressRepositoryInterface;
use SovereignStack\Lms\Learning\Domain\Repository\ModuleProgressRepositoryInterface;

final readonly class LearningProgress implements LearningProgressInterface
{
    public function __construct(
        private RequiredModulesInterface $content,
        private LessonProgressRepositoryInterface $lessons,
        private ModuleProgressRepositoryInterface $modules,
    ) {}

    public function lessonsForEnrollment(string $enrollmentId): array
    {
        return array_map(
            static fn ($progress): array => [
                'lessonId' => $progress->lessonId(),
                'status' => $progress->status()->value,
                'startedAt' => $progress->startedAt()?->format(\DATE_ATOM),
                'completedAt' => $progress->completedAt()?->format(\DATE_ATOM),
                'updatedAt' => $progress->updatedAt()->format(\DATE_ATOM),
            ],
            $this->lessons->listByEnrollment($enrollmentId),
        );
    }

    public function modulesForEnrollment(string $enrollmentId): array
    {
        return array_map(
            static fn ($progress): array => [
                'courseModuleId' => $progress->courseModuleId(),
                'status' => $progress->status()->value,
                'startedAt' => $progress->startedAt()?->format(\DATE_ATOM),
                'completedAt' => $progress->completedAt()?->format(\DATE_ATOM),
                'updatedAt' => $progress->updatedAt()->format(\DATE_ATOM),
            ],
            $this->modules->listByEnrollment($enrollmentId),
        );
    }

    public function incompleteRequiredModuleIds(string $enrollmentId, string $courseVersionId): array
    {
        $incomplete = [];

        foreach ($this->content->requiredModuleIds($courseVersionId) as $moduleId) {
            $progress = $this->modules->findByEnrollmentAndModule($enrollmentId, $moduleId);

            if (null === $progress || ProgressStatus::COMPLETED !== $progress->status()) {
                $incomplete[] = $moduleId;
            }
        }

        return $incomplete;
    }
}
