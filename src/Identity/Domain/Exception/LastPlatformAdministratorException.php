<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Domain\Exception;

use SovereignStack\Lms\Shared\Application\Exception\ConflictException;

final class LastPlatformAdministratorException extends ConflictException
{
    public static function cannotRevoke(): self
    {
        return new self('The last platform administrator role cannot be revoked.');
    }

    public static function cannotSuspend(): self
    {
        return new self('The last active platform administrator cannot be suspended.');
    }

    public static function cannotDeactivate(): self
    {
        return new self('The last active platform administrator cannot be deactivated.');
    }
}
