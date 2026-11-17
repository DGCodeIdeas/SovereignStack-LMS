<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Domain\Exception;

use SovereignStack\Lms\Shared\Application\Exception\ConflictException;

final class AlreadyEnrolledException extends ConflictException
{
    public static function forLearnerAndCourse(string $learnerUserId, string $courseId): self
    {
        return new self(sprintf(
            'Learner "%s" already has an active enrollment for course "%s".',
            $learnerUserId,
            $courseId,
        ));
    }
}
