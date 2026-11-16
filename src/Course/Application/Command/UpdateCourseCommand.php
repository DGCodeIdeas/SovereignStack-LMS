<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Command;

final readonly class UpdateCourseCommand
{
    public function __construct(
        public string $actorUserId,
        public string $courseId,
        public string $title,
        public string $description,
        public string $slug,
        public string $visibility,
    ) {}
}
