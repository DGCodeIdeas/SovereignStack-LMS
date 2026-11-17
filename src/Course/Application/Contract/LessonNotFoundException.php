<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Contract;

use SovereignStack\Lms\Shared\Application\Exception\NotFoundException;

final class LessonNotFoundException extends NotFoundException
{
    public static function forId(string $id): self
    {
        return new self(sprintf('Lesson "%s" was not found.', $id));
    }
}
