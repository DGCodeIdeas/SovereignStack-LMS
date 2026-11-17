<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Contract;

use SovereignStack\Lms\Learning\Application\Contract\LessonProgressRecorderInterface as LearningLessonProgressRecorderInterface;

/**
 * Bridges the learning progress recorder to the enrollment-owned contract so
 * enrollment handlers never import the learning module directly.
 */
final readonly class LessonProgressRecorderAdapter implements LessonProgressRecorderInterface
{
    public function __construct(
        private LearningLessonProgressRecorderInterface $recorder,
    ) {}

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
        $this->recorder->recordLessonProgress(
            $actorUserId,
            $enrollmentId,
            $lessonId,
            $target,
            $organisationId,
            $moduleId,
            $moduleLessonIds,
            $now,
        );
    }
}
