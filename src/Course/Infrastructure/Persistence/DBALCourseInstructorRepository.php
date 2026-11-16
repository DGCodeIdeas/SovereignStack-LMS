<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use SovereignStack\Lms\Course\Domain\Entity\CourseInstructor;
use SovereignStack\Lms\Course\Domain\Repository\CourseInstructorRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseInstructorId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DatabaseValue;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DateTimeCodec;

final readonly class DBALCourseInstructorRepository implements CourseInstructorRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function save(CourseInstructor $instructor): void
    {
        $data = [
            'effective_from' => DateTimeCodec::toDatabase($instructor->effectiveFrom()),
            'effective_until' => DateTimeCodec::toDatabaseNullable($instructor->effectiveUntil()),
        ];

        if ($this->exists($instructor->id())) {
            $this->connection->update('course_instructors', $data, ['id' => $instructor->id()->value()]);

            return;
        }

        $this->connection->insert('course_instructors', $data + [
            'id' => $instructor->id()->value(),
            'course_id' => $instructor->courseId()->value(),
            'user_id' => $instructor->userId()->value(),
            'assigned_by' => $instructor->assignedBy()->value(),
            'assigned_at' => DateTimeCodec::toDatabase($instructor->assignedAt()),
        ]);
    }

    public function findByCourseAndUser(CourseId $courseId, UserIdInterface $userId): ?CourseInstructor
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM course_instructors
             WHERE course_id = :course_id AND user_id = :user_id
             ORDER BY assigned_at DESC
             LIMIT 1',
            ['course_id' => $courseId->value(), 'user_id' => $userId->value()],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function listByCourse(CourseId $courseId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM course_instructors WHERE course_id = :course_id ORDER BY effective_from, assigned_at',
            ['course_id' => $courseId->value()],
        );

        return array_map(fn (array $row): CourseInstructor => $this->hydrate($row), $rows);
    }

    private function exists(CourseInstructorId $id): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM course_instructors WHERE id = :id',
            ['id' => $id->value()],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): CourseInstructor
    {
        return CourseInstructor::restore(
            CourseInstructorId::fromString(DatabaseValue::string($row, 'id')),
            CourseId::fromString(DatabaseValue::string($row, 'course_id')),
            UserIdReference::fromString(DatabaseValue::string($row, 'user_id')),
            UserIdReference::fromString(DatabaseValue::string($row, 'assigned_by')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'assigned_at')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'effective_from')),
            DateTimeCodec::fromDatabaseNullable(DatabaseValue::nullableString($row, 'effective_until')),
        );
    }
}
