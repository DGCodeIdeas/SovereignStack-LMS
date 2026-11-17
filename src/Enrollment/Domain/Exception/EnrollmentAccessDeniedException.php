<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Domain\Exception;

use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;

final class EnrollmentAccessDeniedException extends ForbiddenException
{
    public static function forActor(string $actorId, string $enrollmentId): self
    {
        return new self(sprintf(
            'Actor "%s" is not allowed to access enrollment "%s".',
            $actorId,
            $enrollmentId,
        ));
    }
}
