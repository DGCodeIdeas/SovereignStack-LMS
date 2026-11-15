<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Exception;

use SovereignStack\Lms\Shared\Application\Exception\NotFoundException;

final class CourseModuleNotFoundException extends NotFoundException
{
    public static function forId(string $id): self
    {
        return new self(sprintf('Course module "%s" was not found.', $id));
    }
}
