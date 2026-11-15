<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Entity;

use SovereignStack\Lms\Course\Domain\Exception\InvalidCourseContentException;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseModuleId;
use SovereignStack\Lms\Course\Domain\ValueObject\LessonId;

final class Lesson
{
    private function __construct(
        private LessonId $id,
        private CourseModuleId $courseModuleId,
        private string $title,
        private int $position,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        LessonId $id,
        CourseModuleId $courseModuleId,
        string $title,
        int $position,
        \DateTimeImmutable $now,
    ): self {
        self::assertValidTitle($title);
        self::assertValidPosition($position);

        return new self($id, $courseModuleId, $title, $position, $now, $now);
    }

    public static function restore(
        LessonId $id,
        CourseModuleId $courseModuleId,
        string $title,
        int $position,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        self::assertValidTitle($title);
        self::assertValidPosition($position);

        return new self($id, $courseModuleId, $title, $position, $createdAt, $updatedAt);
    }

    public function rename(string $title, \DateTimeImmutable $now): void
    {
        self::assertValidTitle($title);

        $this->title = $title;
        $this->updatedAt = $now;
    }

    public function changePosition(int $position, \DateTimeImmutable $now): void
    {
        self::assertValidPosition($position);

        $this->position = $position;
        $this->updatedAt = $now;
    }

    public function id(): LessonId
    {
        return $this->id;
    }

    public function courseModuleId(): CourseModuleId
    {
        return $this->courseModuleId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function assertValidTitle(string $title): void
    {
        if ('' === trim($title) || \strlen($title) > 255) {
            throw InvalidCourseContentException::forField('title', 'must be between 1 and 255 characters');
        }
    }

    private static function assertValidPosition(int $position): void
    {
        if ($position < 0) {
            throw InvalidCourseContentException::forField('position', 'must not be negative');
        }
    }
}
