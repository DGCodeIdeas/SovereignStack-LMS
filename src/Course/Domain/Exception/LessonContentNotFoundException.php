<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Exception;

use SovereignStack\Lms\Shared\Application\Exception\NotFoundException;

final class LessonContentNotFoundException extends NotFoundException
{
    public static function forLesson(string $lessonId): self
    {
        return new self(sprintf('Lesson content for lesson "%s" was not found.', $lessonId));
    }
}
