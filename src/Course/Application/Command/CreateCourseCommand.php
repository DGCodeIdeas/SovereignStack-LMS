<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Command;

final readonly class CreateCourseCommand
{
    public function __construct(
        public string $actorUserId,
        public string $organisationId,
        public string $title,
        public string $description,
        public string $slug,
    ) {}
}
