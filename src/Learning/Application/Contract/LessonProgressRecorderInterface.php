<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Application\Contract;

interface LessonProgressRecorderInterface
{
    /**
     * @param list<string> $moduleLessonIds ordered identifiers of every lesson
     *                                      in the module, including unstarted ones
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
    ): void;
}
