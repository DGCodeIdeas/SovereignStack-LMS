<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Domain\Exception;

use SovereignStack\Lms\Shared\Application\Exception\NotFoundException;

final class UserNotFoundException extends NotFoundException
{
    public static function forId(string $id): self
    {
        return new self(sprintf('User "%s" was not found.', $id));
    }

    public static function forEmail(string $email): self
    {
        return new self(sprintf('User with email "%s" was not found.', $email));
    }
}
