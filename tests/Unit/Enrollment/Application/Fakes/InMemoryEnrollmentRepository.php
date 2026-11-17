<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Enrollment\Application\Fakes;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Query;
use SovereignStack\Lms\Enrollment\Domain\Entity\Enrollment;
use SovereignStack\Lms\Enrollment\Domain\Repository\EnrollmentRepositoryInterface;
use SovereignStack\Lms\Enrollment\Domain\ValueObject\EnrollmentId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdInterface;

final class InMemoryEnrollmentRepository implements EnrollmentRepositoryInterface
{
    /**
     * @var array<string, Enrollment>
     */
    public array $enrollments = [];

    public int $saveCount = 0;

    /**
     * Set to true to emulate the database unique index on (learner, course, active).
     */
    public bool $enforceUniqueActive = false;

    public function save(Enrollment $enrollment): void
    {
        ++$this->saveCount;

        if ($this->enforceUniqueActive && isset($this->enrollments[$enrollment->id()->value()])) {
            $this->enrollments[$enrollment->id()->value()] = $enrollment;

            return;
        }

        if ($this->enforceUniqueActive) {
            foreach ($this->enrollments as $existing) {
                if ('active' === $existing->status()->value
                    && 'active' === $enrollment->status()->value
                    && $existing->learnerUserId()->value() === $enrollment->learnerUserId()->value()
                    && $existing->courseId() === $enrollment->courseId()
                ) {
                    throw self::uniqueViolation();
                }
            }
        }

        $this->enrollments[$enrollment->id()->value()] = $enrollment;
    }

    public function findById(EnrollmentId $id): ?Enrollment
    {
        return $this->enrollments[$id->value()] ?? null;
    }

    public function findForUpdate(EnrollmentId $id): ?Enrollment
    {
        return $this->enrollments[$id->value()] ?? null;
    }

    public function findActiveByLearnerAndCourse(UserIdInterface $learnerUserId, string $courseId): ?Enrollment
    {
        foreach ($this->enrollments as $enrollment) {
            if ('active' === $enrollment->status()->value
                && $enrollment->learnerUserId()->value() === $learnerUserId->value()
                && $enrollment->courseId() === $courseId
            ) {
                return $enrollment;
            }
        }

        return null;
    }

    public function listForLearner(UserIdInterface $learnerUserId): array
    {
        $result = [];

        foreach ($this->enrollments as $enrollment) {
            if ($enrollment->learnerUserId()->value() === $learnerUserId->value()) {
                $result[] = $enrollment;
            }
        }

        usort($result, static fn (Enrollment $left, Enrollment $right): int => $right->enrolledAt() <=> $left->enrolledAt());

        return $result;
    }

    public function listForCourse(string $courseId): array
    {
        $result = [];

        foreach ($this->enrollments as $enrollment) {
            if ($enrollment->courseId() === $courseId) {
                $result[] = $enrollment;
            }
        }

        usort($result, static fn (Enrollment $left, Enrollment $right): int => $right->enrolledAt() <=> $left->enrolledAt());

        return $result;
    }

    private static function uniqueViolation(): UniqueConstraintViolationException
    {
        $driverException = new class('Duplicate active enrollment.') extends \RuntimeException implements DriverException {
            public function getSQLState(): ?string
            {
                return '23000';
            }
        };

        return new UniqueConstraintViolationException($driverException, new Query('INSERT INTO enrollments', [], []));
    }
}
