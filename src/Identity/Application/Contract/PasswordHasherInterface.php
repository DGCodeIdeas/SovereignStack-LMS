<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Contract;

interface PasswordHasherInterface
{
    public function hash(string $plain): string;

    public function verify(string $hash, string $plain): bool;
}
