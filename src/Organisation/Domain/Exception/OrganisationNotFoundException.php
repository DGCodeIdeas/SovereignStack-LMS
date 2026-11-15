<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Domain\Exception;

use SovereignStack\Lms\Shared\Application\Exception\NotFoundException;

final class OrganisationNotFoundException extends NotFoundException
{
    public static function forId(string $id): self
    {
        return new self(sprintf('Organisation "%s" was not found.', $id));
    }

    public static function forSlug(string $slug): self
    {
        return new self(sprintf('Organisation with slug "%s" was not found.', $slug));
    }
}
