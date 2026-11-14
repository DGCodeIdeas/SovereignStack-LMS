<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Domain\Exception;

use SovereignStack\Lms\Shared\Application\Exception\ConflictException;

final class EmailAlreadyRegisteredException extends ConflictException
{
    public static function forEmail(string $email): self
    {
        return new self(sprintf('The email address "%s" is already registered.', $email));
    }
}
