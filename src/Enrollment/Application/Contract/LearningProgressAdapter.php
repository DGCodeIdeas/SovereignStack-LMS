<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Contract;

use SovereignStack\Lms\Learning\Application\Contract\LearningProgressInterface as LearningLearningProgressInterface;

/**
 * Bridges the learning progress read model to the enrollment-owned contract so
 * enrollment handlers never import the learning module directly.
 */
final readonly class LearningProgressAdapter implements LearningProgressInterface
{
    public function __construct(
        private LearningLearningProgressInterface $progress,
    ) {}

    public function lessonsForEnrollment(string $enrollmentId): array
    {
        return $this->progress->lessonsForEnrollment($enrollmentId);
    }

    public function modulesForEnrollment(string $enrollmentId): array
    {
        return $this->progress->modulesForEnrollment($enrollmentId);
    }

    public function incompleteRequiredModuleIds(string $enrollmentId, string $courseVersionId): array
    {
        return $this->progress->incompleteRequiredModuleIds($enrollmentId, $courseVersionId);
    }
}
