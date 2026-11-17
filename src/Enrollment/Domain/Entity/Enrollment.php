<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Domain\Entity;

use SovereignStack\Lms\Enrollment\Domain\Enum\EnrollmentStatus;
use SovereignStack\Lms\Enrollment\Domain\Exception\InvalidEnrollmentTransitionException;
use SovereignStack\Lms\Enrollment\Domain\ValueObject\EnrollmentId;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdInterface;

final class Enrollment
{
    private function __construct(
        private EnrollmentId $id,
        private OrganisationIdInterface $organisationId,
        private UserIdInterface $learnerUserId,
        private string $courseId,
        private string $courseVersionId,
        private EnrollmentStatus $status,
        private \DateTimeImmutable $enrolledAt,
        private ?\DateTimeImmutable $completedAt,
        private ?\DateTimeImmutable $cancelledAt,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        EnrollmentId $id,
        OrganisationIdInterface $organisationId,
        UserIdInterface $learnerUserId,
        string $courseId,
        string $courseVersionId,
        \DateTimeImmutable $now,
    ): self {
        self::assertValidIdentifier($courseId, 'course');
        self::assertValidIdentifier($courseVersionId, 'course version');

        return new self(
            $id,
            $organisationId,
            $learnerUserId,
            $courseId,
            $courseVersionId,
            EnrollmentStatus::ACTIVE,
            $now,
            null,
            null,
            $now,
            $now,
        );
    }

    public static function restore(
        EnrollmentId $id,
        OrganisationIdInterface $organisationId,
        UserIdInterface $learnerUserId,
        string $courseId,
        string $courseVersionId,
        EnrollmentStatus $status,
        \DateTimeImmutable $enrolledAt,
        ?\DateTimeImmutable $completedAt,
        ?\DateTimeImmutable $cancelledAt,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            $id,
            $organisationId,
            $learnerUserId,
            $courseId,
            $courseVersionId,
            $status,
            $enrolledAt,
            $completedAt,
            $cancelledAt,
            $createdAt,
            $updatedAt,
        );
    }

    public function complete(\DateTimeImmutable $now): void
    {
        $this->transition(EnrollmentStatus::COMPLETED, [EnrollmentStatus::ACTIVE]);

        $this->status = EnrollmentStatus::COMPLETED;
        $this->completedAt = $now;
        $this->updatedAt = $now;
    }

    public function cancel(\DateTimeImmutable $now): void
    {
        $this->transition(EnrollmentStatus::CANCELLED, [EnrollmentStatus::ACTIVE]);

        $this->status = EnrollmentStatus::CANCELLED;
        $this->cancelledAt = $now;
        $this->updatedAt = $now;
    }

    public function assertActive(): void
    {
        if (EnrollmentStatus::ACTIVE !== $this->status) {
            throw InvalidEnrollmentTransitionException::forTransition(
                $this->status,
                EnrollmentStatus::ACTIVE,
                [EnrollmentStatus::ACTIVE],
            );
        }
    }

    public function id(): EnrollmentId
    {
        return $this->id;
    }

    public function organisationId(): OrganisationIdInterface
    {
        return $this->organisationId;
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

    public function status(): EnrollmentStatus
    {
        return $this->status;
    }

    public function enrolledAt(): \DateTimeImmutable
    {
        return $this->enrolledAt;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function cancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @param list<EnrollmentStatus> $allowed
     */
    private function transition(EnrollmentStatus $target, array $allowed): void
    {
        if (!\in_array($this->status, $allowed, true)) {
            throw InvalidEnrollmentTransitionException::forTransition($this->status, $target, $allowed);
        }
    }

    private static function assertValidIdentifier(string $value, string $label): void
    {
        if ('' === trim($value)) {
            throw new \InvalidArgumentException(sprintf('The %s id cannot be empty.', $label));
        }
    }
}
