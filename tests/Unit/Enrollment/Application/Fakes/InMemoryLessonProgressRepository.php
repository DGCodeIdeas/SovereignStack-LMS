<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Enrollment\Application\Fakes;

use SovereignStack\Lms\Learning\Domain\Entity\LessonProgress;
use SovereignStack\Lms\Learning\Domain\Repository\LessonProgressRepositoryInterface;
use SovereignStack\Lms\Learning\Domain\ValueObject\LessonProgressId;

final class InMemoryLessonProgressRepository implements LessonProgressRepositoryInterface
{
    /**
     * @var array<string, LessonProgress>
     */
    public array $progress = [];

    /**
     * @var array<string, string>
     */
    public array $lessonModules = [];

    public int $saveCount = 0;

    public function save(LessonProgress $progress): void
    {
        ++$this->saveCount;
        $this->progress[$progress->id()->value()] = $progress;
    }

    public function findById(LessonProgressId $id): ?LessonProgress
    {
        return $this->progress[$id->value()] ?? null;
    }

    public function findByEnrollmentAndLesson(string $enrollmentId, string $lessonId): ?LessonProgress
    {
        foreach ($this->progress as $progress) {
            if ($progress->enrollmentId() === $enrollmentId && $progress->lessonId() === $lessonId) {
                return $progress;
            }
        }

        return null;
    }

    public function listByEnrollment(string $enrollmentId): array
    {
        return array_values(array_filter(
            $this->progress,
            static fn (LessonProgress $progress): bool => $progress->enrollmentId() === $enrollmentId,
        ));
    }

    public function listByEnrollmentAndModule(string $enrollmentId, string $courseModuleId): array
    {
        $lessonModules = $this->lessonModules;

        return array_values(array_filter(
            $this->progress,
            static fn (LessonProgress $progress): bool => $progress->enrollmentId() === $enrollmentId
                && ($lessonModules[$progress->lessonId()] ?? null) === $courseModuleId,
        ));
    }
}
