<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use SovereignStack\Lms\Enrollment\Domain\Entity\Enrollment;
use SovereignStack\Lms\Enrollment\Domain\Enum\EnrollmentStatus;
use SovereignStack\Lms\Enrollment\Domain\Repository\EnrollmentRepositoryInterface;
use SovereignStack\Lms\Enrollment\Domain\ValueObject\EnrollmentId;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DatabaseValue;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DateTimeCodec;

final readonly class DBALEnrollmentRepository implements EnrollmentRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function save(Enrollment $enrollment): void
    {
        $data = [
            'status' => $enrollment->status()->value,
            'completed_at' => DateTimeCodec::toDatabaseNullable($enrollment->completedAt()),
            'cancelled_at' => DateTimeCodec::toDatabaseNullable($enrollment->cancelledAt()),
            'updated_at' => DateTimeCodec::toDatabase($enrollment->updatedAt()),
        ];

        if ($this->exists($enrollment->id())) {
            $this->connection->update('enrollments', $data, ['id' => $enrollment->id()->value()]);

            return;
        }

        $this->connection->insert('enrollments', $data + [
            'id' => $enrollment->id()->value(),
            'organisation_id' => $enrollment->organisationId()->value(),
            'learner_user_id' => $enrollment->learnerUserId()->value(),
            'course_id' => $enrollment->courseId(),
            'course_version_id' => $enrollment->courseVersionId(),
            'enrolled_at' => DateTimeCodec::toDatabase($enrollment->enrolledAt()),
            'created_at' => DateTimeCodec::toDatabase($enrollment->createdAt()),
        ]);
    }

    public function findById(EnrollmentId $id): ?Enrollment
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM enrollments WHERE id = :id',
            ['id' => $id->value()],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function findForUpdate(EnrollmentId $id): ?Enrollment
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM enrollments WHERE id = :id FOR UPDATE',
            ['id' => $id->value()],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function findActiveByLearnerAndCourse(UserIdInterface $learnerUserId, string $courseId): ?Enrollment
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM enrollments
             WHERE learner_user_id = :learner_user_id AND course_id = :course_id AND status = :status',
            [
                'learner_user_id' => $learnerUserId->value(),
                'course_id' => $courseId,
                'status' => EnrollmentStatus::ACTIVE->value,
            ],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function listForLearner(UserIdInterface $learnerUserId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM enrollments WHERE learner_user_id = :learner_user_id ORDER BY enrolled_at DESC',
            ['learner_user_id' => $learnerUserId->value()],
        );

        return array_map(fn (array $row): Enrollment => $this->hydrate($row), $rows);
    }

    public function listForCourse(string $courseId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM enrollments WHERE course_id = :course_id ORDER BY enrolled_at DESC',
            ['course_id' => $courseId],
        );

        return array_map(fn (array $row): Enrollment => $this->hydrate($row), $rows);
    }

    private function exists(EnrollmentId $id): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM enrollments WHERE id = :id',
            ['id' => $id->value()],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Enrollment
    {
        return Enrollment::restore(
            EnrollmentId::fromString(DatabaseValue::string($row, 'id')),
            OrganisationIdReference::fromString(DatabaseValue::string($row, 'organisation_id')),
            UserIdReference::fromString(DatabaseValue::string($row, 'learner_user_id')),
            DatabaseValue::string($row, 'course_id'),
            DatabaseValue::string($row, 'course_version_id'),
            EnrollmentStatus::from(DatabaseValue::string($row, 'status')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'enrolled_at')),
            DateTimeCodec::fromDatabaseNullable(DatabaseValue::nullableString($row, 'completed_at')),
            DateTimeCodec::fromDatabaseNullable(DatabaseValue::nullableString($row, 'cancelled_at')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'created_at')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'updated_at')),
        );
    }
}
