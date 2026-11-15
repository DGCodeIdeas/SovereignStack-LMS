<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseCategoryId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseInstructorId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseModuleId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;
use SovereignStack\Lms\Course\Domain\ValueObject\LessonId;

/**
 * @internal
 *
 * @coversNothing
 */
final class CourseIdentifierTest extends TestCase
{
    private const VALID = '01900000-0000-7000-8000-000000003601';
    private const OTHER = '01900000-0000-7000-8000-000000003602';

    /**
     * @param callable(string): string       $valueOf
     * @param callable(string, string): bool $equals
     */
    #[DataProvider('identifiers')]
    public function testAcceptsUuidV7(callable $valueOf, callable $equals): void
    {
        self::assertSame(self::VALID, $valueOf(self::VALID));
    }

    /**
     * @param callable(string): string       $valueOf
     * @param callable(string, string): bool $equals
     */
    #[DataProvider('identifiers')]
    public function testNormalizesCaseAndWhitespace(callable $valueOf, callable $equals): void
    {
        self::assertSame(self::VALID, $valueOf('  '.strtoupper(self::VALID).'  '));
    }

    /**
     * @param callable(string): string       $valueOf
     * @param callable(string, string): bool $equals
     */
    #[DataProvider('identifiers')]
    public function testRejectsUuidV4(callable $valueOf, callable $equals): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $valueOf('550e8400-e29b-41d4-a716-446655440000');
    }

    /**
     * @param callable(string): string       $valueOf
     * @param callable(string, string): bool $equals
     */
    #[DataProvider('identifiers')]
    public function testRejectsMalformedValue(callable $valueOf, callable $equals): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $valueOf('not-a-uuid');
    }

    /**
     * @param callable(string): string       $valueOf
     * @param callable(string, string): bool $equals
     */
    #[DataProvider('identifiers')]
    public function testEqualsComparesValues(callable $valueOf, callable $equals): void
    {
        self::assertTrue($equals(self::VALID, self::VALID));
        self::assertFalse($equals(self::VALID, self::OTHER));
    }

    /**
     * @return iterable<string, array{callable(string): string, callable(string, string): bool}>
     */
    public static function identifiers(): iterable
    {
        yield 'course' => [
            static fn (string $value): string => CourseId::fromString($value)->value(),
            static fn (string $left, string $right): bool => CourseId::fromString($left)->equals(CourseId::fromString($right)),
        ];

        yield 'course version' => [
            static fn (string $value): string => CourseVersionId::fromString($value)->value(),
            static fn (string $left, string $right): bool => CourseVersionId::fromString($left)->equals(CourseVersionId::fromString($right)),
        ];

        yield 'course module' => [
            static fn (string $value): string => CourseModuleId::fromString($value)->value(),
            static fn (string $left, string $right): bool => CourseModuleId::fromString($left)->equals(CourseModuleId::fromString($right)),
        ];

        yield 'lesson' => [
            static fn (string $value): string => LessonId::fromString($value)->value(),
            static fn (string $left, string $right): bool => LessonId::fromString($left)->equals(LessonId::fromString($right)),
        ];

        yield 'course category' => [
            static fn (string $value): string => CourseCategoryId::fromString($value)->value(),
            static fn (string $left, string $right): bool => CourseCategoryId::fromString($left)->equals(CourseCategoryId::fromString($right)),
        ];

        yield 'course instructor' => [
            static fn (string $value): string => CourseInstructorId::fromString($value)->value(),
            static fn (string $left, string $right): bool => CourseInstructorId::fromString($left)->equals(CourseInstructorId::fromString($right)),
        ];
    }
}
