<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Exception;

use SovereignStack\Lms\Shared\Application\Exception\NotFoundException;

final class CategoryNotFoundException extends NotFoundException
{
    public static function forId(string $id): self
    {
        return new self(sprintf('Course category "%s" was not found.', $id));
    }

    public static function forSlug(string $organisationId, string $slug): self
    {
        return new self(sprintf('Course category with slug "%s" was not found for organisation "%s".', $slug, $organisationId));
    }
}
