<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use SovereignStack\Lms\Enrollment\Domain\Entity\CourseCompletion;
use SovereignStack\Lms\Enrollment\Domain\Repository\CourseCompletionRepositoryInterface;
use SovereignStack\Lms\Enrollment\Domain\ValueObject\CourseCompletionId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DatabaseValue;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DateTimeCodec;

final readonly class DBALCourseCompletionRepository implements CourseCompletionRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function save(CourseCompletion $completion): void
    {
        $this->connection->insert('course_completions', [
            'id' => $completion->id()->value(),
            'enrollment_id' => $completion->enrollmentId(),
            'learner_user_id' => $completion->learnerUserId()->value(),
            'course_id' => $completion->courseId(),
            'course_version_id' => $completion->courseVersionId(),
            'completed_at' => DateTimeCodec::toDatabase($completion->completedAt()),
            'created_at' => DateTimeCodec::toDatabase($completion->createdAt()),
        ]);
    }

    public function findByEnrollment(string $enrollmentId): ?CourseCompletion
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM course_completions WHERE enrollment_id = :enrollment_id',
            ['enrollment_id' => $enrollmentId],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function existsForEnrollment(string $enrollmentId): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM course_completions WHERE enrollment_id = :enrollment_id',
            ['enrollment_id' => $enrollmentId],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): CourseCompletion
    {
        return CourseCompletion::restore(
            CourseCompletionId::fromString(DatabaseValue::string($row, 'id')),
            DatabaseValue::string($row, 'enrollment_id'),
            UserIdReference::fromString(DatabaseValue::string($row, 'learner_user_id')),
            DatabaseValue::string($row, 'course_id'),
            DatabaseValue::string($row, 'course_version_id'),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'completed_at')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'created_at')),
        );
    }
}
