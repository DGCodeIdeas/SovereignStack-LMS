<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Enrollment\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Enrollment\Domain\ValueObject\CourseCompletionId;
use SovereignStack\Lms\Enrollment\Domain\ValueObject\EnrollmentId;

/**
 * @internal
 *
 * @coversNothing
 */
final class EnrollmentIdentifierTest extends TestCase
{
    public function testEnrollmentIdNormalizesAndCompares(): void
    {
        $id = EnrollmentId::fromString(' 01900000-0000-7000-8000-00000000400A ');

        self::assertSame('01900000-0000-7000-8000-00000000400a', $id->value());
        self::assertSame('01900000-0000-7000-8000-00000000400a', (string) $id);
        self::assertTrue($id->equals(EnrollmentId::fromString('01900000-0000-7000-8000-00000000400A')));
        self::assertFalse($id->equals(EnrollmentId::fromString('01900000-0000-7000-8000-00000000400b')));
    }

    public function testEnrollmentIdRejectsNonUuidV7(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EnrollmentId::fromString('not-a-uuid');
    }

    public function testCourseCompletionIdNormalizesAndCompares(): void
    {
        $id = CourseCompletionId::fromString('01900000-0000-7000-8000-000000004011');

        self::assertSame('01900000-0000-7000-8000-000000004011', $id->value());
        self::assertSame('01900000-0000-7000-8000-000000004011', (string) $id);
        self::assertTrue($id->equals(CourseCompletionId::fromString('01900000-0000-7000-8000-000000004011')));
        self::assertFalse($id->equals(CourseCompletionId::fromString('01900000-0000-7000-8000-000000004012')));
    }

    public function testCourseCompletionIdRejectsNonUuidV7(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CourseCompletionId::fromString('01900000-0000-1000-8000-000000004013');
    }
}
