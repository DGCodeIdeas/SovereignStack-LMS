<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Support\Fakes;

use SovereignStack\Lms\Identity\Application\Contract\PasswordHasherInterface;

final class FakePasswordHasher implements PasswordHasherInterface
{
    private const PREFIX = 'hashed:';

    public function hash(string $plain): string
    {
        return self::PREFIX.$plain;
    }

    public function verify(string $hash, string $plain): bool
    {
        return hash_equals($hash, self::PREFIX.$plain);
    }
}
