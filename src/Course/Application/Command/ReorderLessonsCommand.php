<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Command;

final readonly class ReorderLessonsCommand
{
    /**
     * @param list<string> $lessonIds
     */
    public function __construct(
        public string $actorUserId,
        public string $courseId,
        public string $moduleId,
        public array $lessonIds,
    ) {}
}
