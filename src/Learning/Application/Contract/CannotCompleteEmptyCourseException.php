<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Application\Contract;

use SovereignStack\Lms\Shared\Application\Exception\ConflictException;

final class CannotCompleteEmptyCourseException extends ConflictException
{
    public static function forCourseVersion(string $versionId): self
    {
        return new self(sprintf('Course version "%s" has no required modules and cannot be completed.', $versionId));
    }
}
