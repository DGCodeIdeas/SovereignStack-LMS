<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Domain\Exception;

use SovereignStack\Lms\Shared\Application\Exception\NotFoundException;

final class LessonProgressNotFoundException extends NotFoundException
{
    public static function forId(string $id): self
    {
        return new self(sprintf('Lesson progress "%s" was not found.', $id));
    }
}
