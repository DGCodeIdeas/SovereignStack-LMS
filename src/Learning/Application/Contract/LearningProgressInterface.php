<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Application\Contract;

interface LearningProgressInterface
{
    /**
     * @return list<array{lessonId: string, status: string, startedAt: null|string, completedAt: null|string, updatedAt: string}>
     */
    public function lessonsForEnrollment(string $enrollmentId): array;

    /**
     * @return list<array{courseModuleId: string, status: string, startedAt: null|string, completedAt: null|string, updatedAt: string}>
     */
    public function modulesForEnrollment(string $enrollmentId): array;

    /**
     * @return list<string>
     */
    public function incompleteRequiredModuleIds(string $enrollmentId, string $courseVersionId): array;
}
