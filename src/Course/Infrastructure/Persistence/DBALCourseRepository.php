<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use SovereignStack\Lms\Course\Domain\Entity\Course;
use SovereignStack\Lms\Course\Domain\Enum\CourseVisibility;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DatabaseValue;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DateTimeCodec;

final readonly class DBALCourseRepository implements CourseRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function save(Course $course): void
    {
        $data = [
            'organisation_id' => $course->organisationId()->value(),
            'owner_user_id' => $course->ownerUserId()->value(),
            'slug' => $course->slug(),
            'visibility' => $course->visibility()->value,
            'updated_at' => DateTimeCodec::toDatabase($course->updatedAt()),
        ];

        if ($this->exists($course->id())) {
            $this->connection->update('courses', $data, ['id' => $course->id()->value()]);

            return;
        }

        $this->connection->insert('courses', $data + [
            'id' => $course->id()->value(),
            'current_published_version_id' => $course->currentPublishedVersionId(),
            'current_published_version_course_id' => $course->currentPublishedVersionCourseId(),
            'created_at' => DateTimeCodec::toDatabase($course->createdAt()),
        ]);
    }

    public function findById(CourseId $id): ?Course
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM courses WHERE id = :id',
            ['id' => $id->value()],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function findByOrganisationAndSlug(OrganisationIdInterface $organisationId, string $slug): ?Course
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM courses WHERE organisation_id = :organisation_id AND slug = :slug',
            ['organisation_id' => $organisationId->value(), 'slug' => $slug],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function listByOrganisation(OrganisationIdInterface $organisationId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM courses WHERE organisation_id = :organisation_id ORDER BY slug',
            ['organisation_id' => $organisationId->value()],
        );

        return array_map(fn (array $row): Course => $this->hydrate($row), $rows);
    }

    public function updatePublishedVersion(Course $course): void
    {
        $versionId = $course->currentPublishedVersionId();
        $versionCourseId = $course->currentPublishedVersionCourseId();

        if (null === $versionId || null === $versionCourseId) {
            throw new \LogicException('updatePublishedVersion() requires a published version id and course id.');
        }

        $this->connection->executeStatement(
            'UPDATE courses
             SET current_published_version_id = :version_id,
                 current_published_version_course_id = :version_course_id,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'version_id' => $versionId,
                'version_course_id' => $versionCourseId,
                'updated_at' => DateTimeCodec::toDatabase($course->updatedAt()),
                'id' => $course->id()->value(),
            ],
        );
    }

    public function clearPublishedVersion(Course $course): void
    {
        $this->connection->executeStatement(
            'UPDATE courses
             SET current_published_version_id = NULL,
                 current_published_version_course_id = NULL,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'updated_at' => DateTimeCodec::toDatabase($course->updatedAt()),
                'id' => $course->id()->value(),
            ],
        );
    }

    private function exists(CourseId $id): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM courses WHERE id = :id',
            ['id' => $id->value()],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Course
    {
        return Course::restore(
            CourseId::fromString(DatabaseValue::string($row, 'id')),
            OrganisationIdReference::fromString(DatabaseValue::string($row, 'organisation_id')),
            UserIdReference::fromString(DatabaseValue::string($row, 'owner_user_id')),
            DatabaseValue::string($row, 'slug'),
            CourseVisibility::from(DatabaseValue::string($row, 'visibility')),
            DatabaseValue::nullableString($row, 'current_published_version_id'),
            DatabaseValue::nullableString($row, 'current_published_version_course_id'),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'created_at')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'updated_at')),
        );
    }
}
