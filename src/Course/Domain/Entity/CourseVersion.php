<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Entity;

use SovereignStack\Lms\Course\Domain\Enum\CourseVersionStatus;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotEditableException;
use SovereignStack\Lms\Course\Domain\Exception\InvalidCourseContentException;
use SovereignStack\Lms\Course\Domain\Exception\InvalidCourseTransitionException;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdInterface;

final class CourseVersion
{
    private function __construct(
        private CourseVersionId $id,
        private CourseId $courseId,
        private int $versionNumber,
        private string $title,
        private ?string $description,
        private CourseVersionStatus $status,
        private ?\DateTimeImmutable $publishedAt,
        private UserIdInterface $createdBy,
        private \DateTimeImmutable $createdAt,
    ) {}

    public static function createDraft(
        CourseVersionId $id,
        CourseId $courseId,
        int $versionNumber,
        string $title,
        ?string $description,
        UserIdInterface $createdBy,
        \DateTimeImmutable $now,
    ): self {
        self::assertValidVersionNumber($versionNumber);
        self::assertValidTitle($title);

        return new self(
            $id,
            $courseId,
            $versionNumber,
            $title,
            $description,
            CourseVersionStatus::DRAFT,
            null,
            $createdBy,
            $now,
        );
    }

    public static function restore(
        CourseVersionId $id,
        CourseId $courseId,
        int $versionNumber,
        string $title,
        ?string $description,
        CourseVersionStatus $status,
        ?\DateTimeImmutable $publishedAt,
        UserIdInterface $createdBy,
        \DateTimeImmutable $createdAt,
    ): self {
        self::assertValidVersionNumber($versionNumber);
        self::assertValidTitle($title);

        return new self($id, $courseId, $versionNumber, $title, $description, $status, $publishedAt, $createdBy, $createdAt);
    }

    public function submitForReview(): void
    {
        $this->transition(CourseVersionStatus::REVIEW, [CourseVersionStatus::DRAFT]);
    }

    public function publish(\DateTimeImmutable $now): void
    {
        $this->transition(CourseVersionStatus::PUBLISHED, [CourseVersionStatus::REVIEW]);

        $this->publishedAt = $now;
    }

    public function rejectFromReview(): void
    {
        $this->transition(CourseVersionStatus::DRAFT, [CourseVersionStatus::REVIEW]);
    }

    public function updateDetails(string $title, ?string $description): void
    {
        $this->assertEditable();
        self::assertValidTitle($title);

        $this->title = $title;
        $this->description = $description;
    }

    public function archive(): void
    {
        $this->transition(CourseVersionStatus::ARCHIVED, [CourseVersionStatus::PUBLISHED]);
    }

    public function assertEditable(): void
    {
        if (CourseVersionStatus::DRAFT !== $this->status) {
            throw CourseNotEditableException::forStatus($this->status);
        }
    }

    public function assertImmutable(): void
    {
        if (CourseVersionStatus::PUBLISHED === $this->status || CourseVersionStatus::ARCHIVED === $this->status) {
            throw CourseNotEditableException::immutable($this->status);
        }
    }

    public function id(): CourseVersionId
    {
        return $this->id;
    }

    public function courseId(): CourseId
    {
        return $this->courseId;
    }

    public function versionNumber(): int
    {
        return $this->versionNumber;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function status(): CourseVersionStatus
    {
        return $this->status;
    }

    public function publishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function createdBy(): UserIdInterface
    {
        return $this->createdBy;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @param list<CourseVersionStatus> $allowed
     */
    private function transition(CourseVersionStatus $target, array $allowed): void
    {
        if (!\in_array($this->status, $allowed, true)) {
            throw InvalidCourseTransitionException::forTransition($this->status, $target, $allowed);
        }

        $this->status = $target;
    }

    private static function assertValidVersionNumber(int $versionNumber): void
    {
        if ($versionNumber < 1) {
            throw InvalidCourseContentException::forField('version_number', 'must be greater than zero');
        }
    }

    private static function assertValidTitle(string $title): void
    {
        if ('' === trim($title) || \strlen($title) > 255) {
            throw InvalidCourseContentException::forField('title', 'must be between 1 and 255 characters');
        }
    }
}
