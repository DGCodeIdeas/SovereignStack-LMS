<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Command;

final readonly class DeactivateUserCommand
{
    public function __construct(
        public string $actorUserId,
        public string $targetUserId,
    ) {}
}
