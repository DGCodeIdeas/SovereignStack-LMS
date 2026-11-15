<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Support;

use SovereignStack\Lms\Course\Domain\Exception\CourseModuleNotFoundException;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotFoundException;
use SovereignStack\Lms\Course\Domain\Exception\LessonNotFoundException;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseModuleId;
use SovereignStack\Lms\Course\Domain\ValueObject\LessonId;

/**
 * Converts external string identifiers into course value objects, translating
 * malformed values into the module's not-found errors instead of leaking
 * low-level argument exceptions.
 */
final class Identifiers
{
    private function __construct() {}

    public static function courseId(string $value): CourseId
    {
        try {
            return CourseId::fromString($value);
        } catch (\InvalidArgumentException) {
            throw CourseNotFoundException::forId($value);
        }
    }

    public static function moduleId(string $value): CourseModuleId
    {
        try {
            return CourseModuleId::fromString($value);
        } catch (\InvalidArgumentException) {
            throw CourseModuleNotFoundException::forId($value);
        }
    }

    public static function lessonId(string $value): LessonId
    {
        try {
            return LessonId::fromString($value);
        } catch (\InvalidArgumentException) {
            throw LessonNotFoundException::forId($value);
        }
    }
}
