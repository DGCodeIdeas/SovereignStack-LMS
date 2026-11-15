<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Domain\Exception;

use SovereignStack\Lms\Shared\Application\Exception\NotFoundException;

final class RoleNotFoundException extends NotFoundException
{
    public static function forCode(string $code): self
    {
        return new self(sprintf('Role "%s" was not found.', $code));
    }
}
