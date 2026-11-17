<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Command;

final readonly class EnrollCommand
{
    public function __construct(
        public string $actorUserId,
        public string $learnerUserId,
        public string $courseId,
    ) {}
}
