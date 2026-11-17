<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Domain\Repository;

use SovereignStack\Lms\Learning\Domain\Entity\LessonProgress;
use SovereignStack\Lms\Learning\Domain\ValueObject\LessonProgressId;

interface LessonProgressRepositoryInterface
{
    public function save(LessonProgress $progress): void;

    public function findById(LessonProgressId $id): ?LessonProgress;

    public function findByEnrollmentAndLesson(string $enrollmentId, string $lessonId): ?LessonProgress;

    /**
     * @return list<LessonProgress>
     */
    public function listByEnrollment(string $enrollmentId): array;

    /**
     * @return list<LessonProgress>
     */
    public function listByEnrollmentAndModule(string $enrollmentId, string $courseModuleId): array;
}
