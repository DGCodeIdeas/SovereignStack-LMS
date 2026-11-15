<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use SovereignStack\Lms\Course\Domain\Entity\CourseCategory;
use SovereignStack\Lms\Course\Domain\Repository\CourseCategoryRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseCategoryId;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DatabaseValue;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DateTimeCodec;

final readonly class DBALCourseCategoryRepository implements CourseCategoryRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function save(CourseCategory $category): void
    {
        $data = [
            'name' => $category->name(),
            'slug' => $category->slug(),
        ];

        if ($this->exists($category->id())) {
            $this->connection->update('course_categories', $data, ['id' => $category->id()->value()]);

            return;
        }

        $this->connection->insert('course_categories', $data + [
            'id' => $category->id()->value(),
            'organisation_id' => $category->organisationId()->value(),
            'created_at' => DateTimeCodec::toDatabase($category->createdAt()),
        ]);
    }

    public function findById(CourseCategoryId $id): ?CourseCategory
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM course_categories WHERE id = :id',
            ['id' => $id->value()],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function findByOrganisationAndSlug(OrganisationIdInterface $organisationId, string $slug): ?CourseCategory
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM course_categories WHERE organisation_id = :organisation_id AND slug = :slug',
            ['organisation_id' => $organisationId->value(), 'slug' => $slug],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function listByOrganisation(OrganisationIdInterface $organisationId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM course_categories WHERE organisation_id = :organisation_id ORDER BY name',
            ['organisation_id' => $organisationId->value()],
        );

        return array_map(fn (array $row): CourseCategory => $this->hydrate($row), $rows);
    }

    private function exists(CourseCategoryId $id): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM course_categories WHERE id = :id',
            ['id' => $id->value()],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): CourseCategory
    {
        return CourseCategory::restore(
            CourseCategoryId::fromString(DatabaseValue::string($row, 'id')),
            OrganisationIdReference::fromString(DatabaseValue::string($row, 'organisation_id')),
            DatabaseValue::string($row, 'name'),
            DatabaseValue::string($row, 'slug'),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'created_at')),
        );
    }
}
