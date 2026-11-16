<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Command;

final readonly class CreateDraftVersionCommand
{
    public function __construct(
        public string $actorUserId,
        public string $courseId,
    ) {}
}
