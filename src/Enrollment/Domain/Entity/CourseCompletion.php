<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Domain\Entity;

use SovereignStack\Lms\Enrollment\Domain\ValueObject\CourseCompletionId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdInterface;

final class CourseCompletion
{
    private function __construct(
        private CourseCompletionId $id,
        private string $enrollmentId,
        private UserIdInterface $learnerUserId,
        private string $courseId,
        private string $courseVersionId,
        private \DateTimeImmutable $completedAt,
        private \DateTimeImmutable $createdAt,
    ) {}

    public static function record(
        CourseCompletionId $id,
        string $enrollmentId,
        UserIdInterface $learnerUserId,
        string $courseId,
        string $courseVersionId,
        \DateTimeImmutable $completedAt,
    ): self {
        self::assertValidIdentifier($enrollmentId, 'enrollment');
        self::assertValidIdentifier($courseId, 'course');
        self::assertValidIdentifier($courseVersionId, 'course version');

        return new self($id, $enrollmentId, $learnerUserId, $courseId, $courseVersionId, $completedAt, $completedAt);
    }

    public static function restore(
        CourseCompletionId $id,
        string $enrollmentId,
        UserIdInterface $learnerUserId,
        string $courseId,
        string $courseVersionId,
        \DateTimeImmutable $completedAt,
        \DateTimeImmutable $createdAt,
    ): self {
        self::assertValidIdentifier($enrollmentId, 'enrollment');
        self::assertValidIdentifier($courseId, 'course');
        self::assertValidIdentifier($courseVersionId, 'course version');

        return new self(
            $id,
            $enrollmentId,
            $learnerUserId,
            $courseId,
            $courseVersionId,
            $completedAt,
            $createdAt,
        );
    }

    public function id(): CourseCompletionId
    {
        return $this->id;
    }

    public function enrollmentId(): string
    {
        return $this->enrollmentId;
    }

    public function learnerUserId(): UserIdInterface
    {
        return $this->learnerUserId;
    }

    public function courseId(): string
    {
        return $this->courseId;
    }

    public function courseVersionId(): string
    {
        return $this->courseVersionId;
    }

    public function completedAt(): \DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private static function assertValidIdentifier(string $value, string $label): void
    {
        if ('' === trim($value)) {
            throw new \InvalidArgumentException(sprintf('The %s id cannot be empty.', $label));
        }
    }
}
