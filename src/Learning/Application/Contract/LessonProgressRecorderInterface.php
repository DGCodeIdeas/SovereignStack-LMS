<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Application\Contract;

interface LessonProgressRecorderInterface
{
    public function recordLessonProgress(
        string $actorUserId,
        string $enrollmentId,
        string $lessonId,
        string $target,
        string $organisationId,
        string $moduleId,
        \DateTimeImmutable $now,
    ): void;
}
