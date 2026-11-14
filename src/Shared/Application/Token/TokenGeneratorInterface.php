<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Application\Token;

interface TokenGeneratorInterface
{
    public function generate(): string;
}
