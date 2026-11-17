<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use SovereignStack\Lms\Learning\Domain\Entity\LessonProgress;
use SovereignStack\Lms\Learning\Domain\Enum\ProgressStatus;
use SovereignStack\Lms\Learning\Domain\Exception\ProgressConflictException;
use SovereignStack\Lms\Learning\Domain\Repository\LessonProgressRepositoryInterface;
use SovereignStack\Lms\Learning\Domain\ValueObject\LessonProgressId;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DatabaseValue;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DateTimeCodec;

final readonly class DBALLessonProgressRepository implements LessonProgressRepositoryInterface
{
    public function __construct(private Connection $connection) {}

    public function save(LessonProgress $progress): void
    {
        $isNew = null === $this->findById($progress->id());
        $now = DateTimeCodec::toDatabase($progress->updatedAt());

        try {
            if ($isNew) {
                $this->connection->insert('lesson_progress', [
                    'id' => $progress->id()->value(),
                    'enrollment_id' => $progress->enrollmentId(),
                    'lesson_id' => $progress->lessonId(),
                    'status' => $progress->status()->value,
                    'progress_data' => DatabaseValue::encodeJson($progress->progressData()),
                    'started_at' => DateTimeCodec::toDatabaseNullable($progress->startedAt()),
                    'completed_at' => DateTimeCodec::toDatabaseNullable($progress->completedAt()),
                    'updated_at' => $now,
                    'version' => $progress->version(),
                ]);

                return;
            }

            $updated = $this->connection->update(
                'lesson_progress',
                [
                    'status' => $progress->status()->value,
                    'progress_data' => DatabaseValue::encodeJson($progress->progressData()),
                    'started_at' => DateTimeCodec::toDatabaseNullable($progress->startedAt()),
                    'completed_at' => DateTimeCodec::toDatabaseNullable($progress->completedAt()),
                    'updated_at' => $now,
                    'version' => $progress->version() + 1,
                ],
                [
                    'id' => $progress->id()->value(),
                    'version' => $progress->version(),
                ],
            );

            if (1 !== $updated) {
                throw ProgressConflictException::versionMismatch(
                    $progress->id()->value(),
                    $progress->version(),
                    $progress->version(),
                );
            }
        } catch (UniqueConstraintViolationException $exception) {
            throw $exception;
        }
    }

    public function findById(LessonProgressId $id): ?LessonProgress
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM lesson_progress WHERE id = :id', [
            'id' => $id->value(),
        ]);

        return false === $row ? null : $this->hydrate($row);
    }

    public function findByEnrollmentAndLesson(string $enrollmentId, string $lessonId): ?LessonProgress
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM lesson_progress WHERE enrollment_id = :enrollment AND lesson_id = :lesson',
            [
                'enrollment' => $enrollmentId,
                'lesson' => $lessonId,
            ],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function listByEnrollment(string $enrollmentId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM lesson_progress WHERE enrollment_id = :enrollment ORDER BY id ASC',
            ['enrollment' => $enrollmentId],
        );

        return array_map(fn (array $row): LessonProgress => $this->hydrate($row), $rows);
    }

    public function listByEnrollmentAndModule(string $enrollmentId, string $courseModuleId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT lp.* FROM lesson_progress lp
             INNER JOIN lessons l ON l.id = lp.lesson_id
             INNER JOIN course_modules cm ON cm.id = l.course_module_id
             WHERE lp.enrollment_id = :enrollment AND cm.id = :module
             ORDER BY lp.id ASC',
            [
                'enrollment' => $enrollmentId,
                'module' => $courseModuleId,
            ],
        );

        return array_map(fn (array $row): LessonProgress => $this->hydrate($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): LessonProgress
    {
        return LessonProgress::restore(
            LessonProgressId::fromString(DatabaseValue::string($row, 'id')),
            DatabaseValue::string($row, 'enrollment_id'),
            DatabaseValue::string($row, 'lesson_id'),
            ProgressStatus::from(DatabaseValue::string($row, 'status')),
            DatabaseValue::decodeJson(DatabaseValue::nullableString($row, 'progress_data')),
            DateTimeCodec::fromDatabaseNullable(DatabaseValue::nullableString($row, 'started_at')),
            DateTimeCodec::fromDatabaseNullable(DatabaseValue::nullableString($row, 'completed_at')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'updated_at')),
            DatabaseValue::int($row, 'version'),
        );
    }
}
