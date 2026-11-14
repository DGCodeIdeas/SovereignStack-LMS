<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Domain\Exception;

use SovereignStack\Lms\Shared\Domain\Exception\DomainException;

final class TokenInvalidException extends DomainException
{
    public static function alreadyConsumed(): self
    {
        return new self('The token has already been consumed.');
    }

    public static function unknown(): self
    {
        return new self('The token is invalid.');
    }
}
