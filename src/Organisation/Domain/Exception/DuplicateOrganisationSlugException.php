<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Domain\Exception;

use SovereignStack\Lms\Shared\Application\Exception\ConflictException;

final class DuplicateOrganisationSlugException extends ConflictException
{
    public static function forSlug(string $slug): self
    {
        return new self(sprintf('An organisation with slug "%s" already exists.', $slug));
    }
}
