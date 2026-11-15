<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Functional\Fixtures;

use SovereignStack\Lms\Shared\Application\Token\TokenGeneratorInterface;

final class DeterministicTokenGenerator implements TokenGeneratorInterface
{
    private static int $counter = 0;

    private static ?string $lastToken = null;

    public function generate(): string
    {
        ++self::$counter;
        self::$lastToken = sprintf('test-token-%d', self::$counter);

        return self::$lastToken;
    }

    public static function lastToken(): ?string
    {
        return self::$lastToken;
    }

    public static function reset(): void
    {
        self::$counter = 0;
        self::$lastToken = null;
    }
}
