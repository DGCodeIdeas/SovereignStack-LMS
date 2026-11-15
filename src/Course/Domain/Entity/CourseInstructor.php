<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Entity;

use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseInstructorId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdInterface;

final class CourseInstructor
{
    private function __construct(
        private CourseInstructorId $id,
        private CourseId $courseId,
        private UserIdInterface $userId,
        private UserIdInterface $assignedBy,
        private \DateTimeImmutable $assignedAt,
        private \DateTimeImmutable $effectiveFrom,
        private ?\DateTimeImmutable $effectiveUntil,
    ) {}

    public static function assign(
        CourseInstructorId $id,
        CourseId $courseId,
        UserIdInterface $userId,
        UserIdInterface $assignedBy,
        \DateTimeImmutable $assignedAt,
        \DateTimeImmutable $effectiveFrom,
        ?\DateTimeImmutable $effectiveUntil,
    ): self {
        self::assertValidWindow($effectiveFrom, $effectiveUntil);

        return new self($id, $courseId, $userId, $assignedBy, $assignedAt, $effectiveFrom, $effectiveUntil);
    }

    public static function restore(
        CourseInstructorId $id,
        CourseId $courseId,
        UserIdInterface $userId,
        UserIdInterface $assignedBy,
        \DateTimeImmutable $assignedAt,
        \DateTimeImmutable $effectiveFrom,
        ?\DateTimeImmutable $effectiveUntil,
    ): self {
        self::assertValidWindow($effectiveFrom, $effectiveUntil);

        return new self($id, $courseId, $userId, $assignedBy, $assignedAt, $effectiveFrom, $effectiveUntil);
    }

    public function isEffectiveAt(\DateTimeImmutable $now): bool
    {
        if ($now < $this->effectiveFrom) {
            return false;
        }

        return null === $this->effectiveUntil || $now < $this->effectiveUntil;
    }

    public function id(): CourseInstructorId
    {
        return $this->id;
    }

    public function courseId(): CourseId
    {
        return $this->courseId;
    }

    public function userId(): UserIdInterface
    {
        return $this->userId;
    }

    public function assignedBy(): UserIdInterface
    {
        return $this->assignedBy;
    }

    public function assignedAt(): \DateTimeImmutable
    {
        return $this->assignedAt;
    }

    public function effectiveFrom(): \DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function effectiveUntil(): ?\DateTimeImmutable
    {
        return $this->effectiveUntil;
    }

    private static function assertValidWindow(\DateTimeImmutable $effectiveFrom, ?\DateTimeImmutable $effectiveUntil): void
    {
        if (null !== $effectiveUntil && $effectiveUntil <= $effectiveFrom) {
            throw new \InvalidArgumentException('The instructor assignment must end after it becomes effective.');
        }
    }
}
