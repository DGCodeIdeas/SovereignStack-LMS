<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Command;

final readonly class AssignInstructorCommand
{
    public function __construct(
        public string $actorUserId,
        public string $courseId,
        public string $instructorUserId,
        public string $effectiveFrom,
        public ?string $effectiveUntil = null,
    ) {}
}
