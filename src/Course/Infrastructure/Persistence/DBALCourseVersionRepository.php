<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use SovereignStack\Lms\Course\Domain\Entity\CourseVersion;
use SovereignStack\Lms\Course\Domain\Enum\CourseVersionStatus;
use SovereignStack\Lms\Course\Domain\Repository\CourseVersionRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DatabaseValue;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DateTimeCodec;

final readonly class DBALCourseVersionRepository implements CourseVersionRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function save(CourseVersion $version): void
    {
        $data = [
            'title' => $version->title(),
            'description' => $version->description(),
            'status' => $version->status()->value,
            'published_at' => DateTimeCodec::toDatabaseNullable($version->publishedAt()),
        ];

        if ($this->exists($version->id())) {
            $this->connection->update('course_versions', $data, ['id' => $version->id()->value()]);

            return;
        }

        $this->connection->insert('course_versions', $data + [
            'id' => $version->id()->value(),
            'course_id' => $version->courseId()->value(),
            'version_number' => $version->versionNumber(),
            'created_by' => $version->createdBy()->value(),
            'created_at' => DateTimeCodec::toDatabase($version->createdAt()),
        ]);
    }

    public function findById(CourseVersionId $id): ?CourseVersion
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM course_versions WHERE id = :id',
            ['id' => $id->value()],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function findDraftByCourse(CourseId $courseId): ?CourseVersion
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM course_versions
             WHERE course_id = :course_id AND status = :status
             ORDER BY version_number DESC
             LIMIT 1',
            ['course_id' => $courseId->value(), 'status' => CourseVersionStatus::DRAFT->value],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function nextVersionNumber(CourseId $courseId): int
    {
        $value = $this->connection->fetchOne(
            'SELECT COALESCE(MAX(version_number), 0) + 1 FROM course_versions WHERE course_id = :course_id',
            ['course_id' => $courseId->value()],
        );

        if (!is_numeric($value)) {
            throw new \UnexpectedValueException(sprintf('Expected a numeric next version number, got %s.', get_debug_type($value)));
        }

        return (int) $value;
    }

    public function listByCourse(CourseId $courseId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM course_versions WHERE course_id = :course_id ORDER BY version_number',
            ['course_id' => $courseId->value()],
        );

        return array_map(fn (array $row): CourseVersion => $this->hydrate($row), $rows);
    }

    private function exists(CourseVersionId $id): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM course_versions WHERE id = :id',
            ['id' => $id->value()],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): CourseVersion
    {
        return CourseVersion::restore(
            CourseVersionId::fromString(DatabaseValue::string($row, 'id')),
            CourseId::fromString(DatabaseValue::string($row, 'course_id')),
            DatabaseValue::int($row, 'version_number'),
            DatabaseValue::string($row, 'title'),
            DatabaseValue::nullableString($row, 'description'),
            CourseVersionStatus::from(DatabaseValue::string($row, 'status')),
            DateTimeCodec::fromDatabaseNullable(DatabaseValue::nullableString($row, 'published_at')),
            UserIdReference::fromString(DatabaseValue::string($row, 'created_by')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'created_at')),
        );
    }
}
