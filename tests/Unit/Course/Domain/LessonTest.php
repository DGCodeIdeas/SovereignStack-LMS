<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Course\Domain\Entity\Lesson;
use SovereignStack\Lms\Course\Domain\Exception\InvalidCourseContentException;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseModuleId;
use SovereignStack\Lms\Course\Domain\ValueObject\LessonId;

/**
 * @internal
 *
 * @coversNothing
 */
final class LessonTest extends TestCase
{
    private const ID = '01900000-0000-7000-8000-000000003301';
    private const MODULE_ID = '01900000-0000-7000-8000-000000003302';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-15T10:00:00+00:00');
    }

    public function testCreateStoresLesson(): void
    {
        $lesson = $this->create();

        self::assertSame(self::ID, $lesson->id()->value());
        self::assertSame(self::MODULE_ID, $lesson->courseModuleId()->value());
        self::assertSame('Welcome', $lesson->title());
        self::assertSame(0, $lesson->position());
        self::assertEquals($this->now, $lesson->createdAt());
        self::assertEquals($this->now, $lesson->updatedAt());
    }

    public function testRejectsEmptyTitle(): void
    {
        $this->expectException(InvalidCourseContentException::class);

        $this->create('   ');
    }

    public function testRejectsTitleLongerThan255Characters(): void
    {
        $this->expectException(InvalidCourseContentException::class);

        $this->create(str_repeat('b', 256));
    }

    public function testRejectsNegativePosition(): void
    {
        $this->expectException(InvalidCourseContentException::class);

        $this->create('Welcome', -1);
    }

    public function testRenameAndChangePositionUpdateTimestamp(): void
    {
        $lesson = $this->create();
        $movedAt = $this->now->modify('+1 hour');

        $lesson->rename('Welcome Aboard', $movedAt);
        $lesson->changePosition(2, $movedAt->modify('+1 hour'));

        self::assertSame('Welcome Aboard', $lesson->title());
        self::assertSame(2, $lesson->position());
        self::assertEquals($movedAt->modify('+1 hour'), $lesson->updatedAt());

        $this->expectException(InvalidCourseContentException::class);

        $lesson->changePosition(-1, $movedAt);
    }

    private function create(string $title = 'Welcome', int $position = 0): Lesson
    {
        return Lesson::create(
            LessonId::fromString(self::ID),
            CourseModuleId::fromString(self::MODULE_ID),
            $title,
            $position,
            $this->now,
        );
    }
}
