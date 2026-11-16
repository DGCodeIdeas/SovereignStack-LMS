<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Exception;

use SovereignStack\Lms\Shared\Application\Exception\NotFoundException;

final class CourseVersionNotFoundException extends NotFoundException
{
    public static function forId(string $id): self
    {
        return new self(sprintf('Course version "%s" was not found.', $id));
    }

    public static function draftNotFound(string $courseId): self
    {
        return new self(sprintf('No draft course version exists for course "%s".', $courseId));
    }

    public static function publishedNotFound(string $courseId): self
    {
        return new self(sprintf('No published course version exists for course "%s".', $courseId));
    }
}
