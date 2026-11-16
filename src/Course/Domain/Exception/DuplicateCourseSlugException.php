<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Exception;

use SovereignStack\Lms\Shared\Application\Exception\ConflictException;

final class DuplicateCourseSlugException extends ConflictException
{
    public static function forSlug(string $organisationId, string $slug): self
    {
        return new self(sprintf('A course with slug "%s" already exists for organisation "%s".', $slug, $organisationId));
    }
}
