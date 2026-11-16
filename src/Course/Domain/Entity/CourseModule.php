<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Entity;

use SovereignStack\Lms\Course\Domain\Exception\InvalidCourseContentException;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseModuleId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;

final class CourseModule
{
    private function __construct(
        private CourseModuleId $id,
        private CourseVersionId $courseVersionId,
        private string $title,
        private ?string $description,
        private int $position,
        private bool $isRequired,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        CourseModuleId $id,
        CourseVersionId $courseVersionId,
        string $title,
        ?string $description,
        int $position,
        bool $isRequired,
        \DateTimeImmutable $now,
    ): self {
        self::assertValidTitle($title);
        self::assertValidPosition($position);

        return new self($id, $courseVersionId, $title, $description, $position, $isRequired, $now, $now);
    }

    public static function restore(
        CourseModuleId $id,
        CourseVersionId $courseVersionId,
        string $title,
        ?string $description,
        int $position,
        bool $isRequired,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        self::assertValidTitle($title);
        self::assertValidPosition($position);

        return new self($id, $courseVersionId, $title, $description, $position, $isRequired, $createdAt, $updatedAt);
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

    public function changeRequirement(bool $isRequired, \DateTimeImmutable $now): void
    {
        $this->isRequired = $isRequired;
        $this->updatedAt = $now;
    }

    public function id(): CourseModuleId
    {
        return $this->id;
    }

    public function courseVersionId(): CourseVersionId
    {
        return $this->courseVersionId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
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
