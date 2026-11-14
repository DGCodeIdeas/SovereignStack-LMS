<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Command;

final readonly class ResetPasswordCommand
{
    public function __construct(
        public string $rawToken,
        public string $plainPassword,
    ) {}
}
