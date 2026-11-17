<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Domain\Exception;

use SovereignStack\Lms\Shared\Domain\Exception\DomainException;

final class CourseNotPublishedException extends DomainException
{
    public static function forCourse(string $courseId): self
    {
        return new self(sprintf('Course "%s" has no published version and cannot be enrolled in.', $courseId));
    }
}
