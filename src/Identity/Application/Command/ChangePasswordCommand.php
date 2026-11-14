<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Command;

final readonly class ChangePasswordCommand
{
    public function __construct(
        public string $userId,
        public string $currentPassword,
        public string $newPassword,
    ) {}
}
