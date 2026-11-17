<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Enrollment\Application\Fakes;

use SovereignStack\Lms\Enrollment\Application\Contract\LessonProgressRecorderInterface;
use SovereignStack\Lms\Learning\Domain\Exception\ProgressConflictException;

final class FlakyLessonProgressRecorder implements LessonProgressRecorderInterface
{
    public int $attempts = 0;

    public function __construct(
        private LessonProgressRecorderInterface $inner,
        private int $failures = 1,
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
        ++$this->attempts;

        if ($this->attempts <= $this->failures) {
            throw ProgressConflictException::versionMismatch('lesson_progress', 1, 2);
        }

        $this->inner->recordLessonProgress(
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
