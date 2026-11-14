<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Domain\Exception;

use SovereignStack\Lms\Shared\Domain\Exception\DomainException;

final class InvalidAccountStateException extends DomainException
{
    public static function forTransition(string $from, string $to): self
    {
        return new self(sprintf('Cannot transition an account from "%s" to "%s".', $from, $to));
    }
}
