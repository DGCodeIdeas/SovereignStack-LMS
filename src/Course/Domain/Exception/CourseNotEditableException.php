<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Exception;

use SovereignStack\Lms\Course\Domain\Enum\CourseVersionStatus;
use SovereignStack\Lms\Shared\Domain\Exception\DomainException;

final class CourseNotEditableException extends DomainException
{
    public static function forStatus(CourseVersionStatus $status): self
    {
        return new self(sprintf('Course versions in status "%s" cannot be edited; only drafts are editable.', $status->value));
    }

    public static function immutable(CourseVersionStatus $status): self
    {
        return new self(sprintf('Course versions in status "%s" are immutable.', $status->value));
    }
}
