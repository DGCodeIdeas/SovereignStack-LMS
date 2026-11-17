<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use SovereignStack\Lms\Learning\Domain\Entity\ModuleProgress;
use SovereignStack\Lms\Learning\Domain\Enum\ProgressStatus;
use SovereignStack\Lms\Learning\Domain\Exception\ProgressConflictException;
use SovereignStack\Lms\Learning\Domain\Repository\ModuleProgressRepositoryInterface;
use SovereignStack\Lms\Learning\Domain\ValueObject\ModuleProgressId;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DatabaseValue;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DateTimeCodec;

final readonly class DBALModuleProgressRepository implements ModuleProgressRepositoryInterface
{
    public function __construct(private Connection $connection) {}

    public function save(ModuleProgress $progress): void
    {
        $isNew = null === $this->findById($progress->id());
        $now = DateTimeCodec::toDatabase($progress->updatedAt());

        if ($isNew) {
            $this->connection->insert('module_progress', [
                'id' => $progress->id()->value(),
                'enrollment_id' => $progress->enrollmentId(),
                'course_module_id' => $progress->courseModuleId(),
                'status' => $progress->status()->value,
                'started_at' => DateTimeCodec::toDatabaseNullable($progress->startedAt()),
                'completed_at' => DateTimeCodec::toDatabaseNullable($progress->completedAt()),
                'updated_at' => $now,
                'version' => $progress->version(),
            ]);

            return;
        }

        $updated = $this->connection->update(
            'module_progress',
            [
                'status' => $progress->status()->value,
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
    }

    public function findById(ModuleProgressId $id): ?ModuleProgress
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM module_progress WHERE id = :id', [
            'id' => $id->value(),
        ]);

        return false === $row ? null : $this->hydrate($row);
    }

    public function findByEnrollmentAndModule(string $enrollmentId, string $courseModuleId): ?ModuleProgress
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM module_progress WHERE enrollment_id = :enrollment AND course_module_id = :module',
            [
                'enrollment' => $enrollmentId,
                'module' => $courseModuleId,
            ],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function findForUpdate(string $enrollmentId, string $courseModuleId): ?ModuleProgress
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM module_progress WHERE enrollment_id = :enrollment AND course_module_id = :module FOR UPDATE',
            [
                'enrollment' => $enrollmentId,
                'module' => $courseModuleId,
            ],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function listByEnrollment(string $enrollmentId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM module_progress WHERE enrollment_id = :enrollment ORDER BY id ASC',
            ['enrollment' => $enrollmentId],
        );

        return array_map(fn (array $row): ModuleProgress => $this->hydrate($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ModuleProgress
    {
        return ModuleProgress::restore(
            ModuleProgressId::fromString(DatabaseValue::string($row, 'id')),
            DatabaseValue::string($row, 'enrollment_id'),
            DatabaseValue::string($row, 'course_module_id'),
            ProgressStatus::from(DatabaseValue::string($row, 'status')),
            DateTimeCodec::fromDatabaseNullable(DatabaseValue::nullableString($row, 'started_at')),
            DateTimeCodec::fromDatabaseNullable(DatabaseValue::nullableString($row, 'completed_at')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'updated_at')),
            DatabaseValue::int($row, 'version'),
        );
    }
}
