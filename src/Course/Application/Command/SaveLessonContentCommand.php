<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Command;

final readonly class SaveLessonContentCommand
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $actorUserId,
        public string $courseId,
        public string $lessonId,
        public string $contentHtml,
        public array $metadata = [],
    ) {}
}
