<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Command;

final readonly class AddModuleCommand
{
    public function __construct(
        public string $actorUserId,
        public string $courseId,
        public string $title,
        public string $description,
        public bool $isRequired,
    ) {}
}
