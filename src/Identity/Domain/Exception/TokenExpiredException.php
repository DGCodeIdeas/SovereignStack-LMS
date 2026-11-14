<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Domain\Exception;

use SovereignStack\Lms\Shared\Domain\Exception\DomainException;

final class TokenExpiredException extends DomainException
{
    public static function at(\DateTimeImmutable $expiresAt): self
    {
        return new self(sprintf('The token expired at %s.', $expiresAt->format(\DATE_ATOM)));
    }
}
