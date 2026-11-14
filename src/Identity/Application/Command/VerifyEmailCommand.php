<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Command;

final readonly class VerifyEmailCommand
{
    public function __construct(
        public string $rawToken,
    ) {}
}
