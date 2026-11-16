<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Exception;

use SovereignStack\Lms\Shared\Application\Exception\NotFoundException;

final class InstructorNotFoundException extends NotFoundException
{
    public static function forCourseAndUser(string $courseId, string $userId): self
    {
        return new self(sprintf('No instructor assignment exists for user "%s" on course "%s".', $userId, $courseId));
    }
}
