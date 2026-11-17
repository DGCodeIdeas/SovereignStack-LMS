<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Domain\Exception;

use SovereignStack\Lms\Shared\Application\Exception\NotFoundException;

final class EnrollmentNotFoundException extends NotFoundException
{
    public static function forId(string $id): self
    {
        return new self(sprintf('Enrollment "%s" was not found.', $id));
    }
}
