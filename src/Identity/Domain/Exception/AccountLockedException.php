<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Domain\Exception;

use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;

final class AccountLockedException extends ForbiddenException
{
    public static function until(\DateTimeImmutable $lockedUntil): self
    {
        return new self(sprintf('The account is locked until %s.', $lockedUntil->format(\DATE_ATOM)));
    }
}
