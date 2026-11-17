<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Command;

final readonly class RecordLessonProgressCommand
{
    public function __construct(
        public string $actorUserId,
        public string $enrollmentId,
        public string $lessonId,
        public string $status,
    ) {}
}
