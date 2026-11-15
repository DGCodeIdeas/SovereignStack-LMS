<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Infrastructure\Token;

use SovereignStack\Lms\Shared\Application\Token\TokenGeneratorInterface;

final readonly class RandomTokenGenerator implements TokenGeneratorInterface
{
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}
