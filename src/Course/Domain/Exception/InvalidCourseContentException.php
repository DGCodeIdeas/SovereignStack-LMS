<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Exception;

use SovereignStack\Lms\Shared\Domain\Exception\DomainException;

final class InvalidCourseContentException extends DomainException
{
    public static function forField(string $field, string $reason): self
    {
        return new self(sprintf('Invalid course content field "%s": %s.', $field, $reason));
    }
}
