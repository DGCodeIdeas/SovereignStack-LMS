<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use SovereignStack\Lms\Course\Domain\Entity\CourseModule;
use SovereignStack\Lms\Course\Domain\Repository\CourseModuleRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseModuleId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DatabaseValue;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DateTimeCodec;

final readonly class DBALCourseModuleRepository implements CourseModuleRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function save(CourseModule $module): void
    {
        $data = [
            'title' => $module->title(),
            'description' => $module->description(),
            'position' => $module->position(),
            'is_required' => $module->isRequired() ? 1 : 0,
            'updated_at' => DateTimeCodec::toDatabase($module->updatedAt()),
        ];

        if ($this->exists($module->id())) {
            $this->connection->update('course_modules', $data, ['id' => $module->id()->value()]);

            return;
        }

        $this->connection->insert('course_modules', $data + [
            'id' => $module->id()->value(),
            'course_version_id' => $module->courseVersionId()->value(),
            'created_at' => DateTimeCodec::toDatabase($module->createdAt()),
        ]);
    }

    public function findById(CourseModuleId $id): ?CourseModule
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM course_modules WHERE id = :id',
            ['id' => $id->value()],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function listByVersion(CourseVersionId $courseVersionId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM course_modules WHERE course_version_id = :course_version_id ORDER BY position',
            ['course_version_id' => $courseVersionId->value()],
        );

        return array_map(fn (array $row): CourseModule => $this->hydrate($row), $rows);
    }

    public function delete(CourseModuleId $id): void
    {
        $this->connection->executeStatement(
            'DELETE FROM course_modules WHERE id = :id',
            ['id' => $id->value()],
        );
    }

    public function nextPosition(CourseVersionId $courseVersionId): int
    {
        $value = $this->connection->fetchOne(
            'SELECT COALESCE(MAX(position), -1) + 1 FROM course_modules WHERE course_version_id = :course_version_id',
            ['course_version_id' => $courseVersionId->value()],
        );

        if (!is_numeric($value)) {
            throw new \UnexpectedValueException(sprintf('Expected a numeric next position, got %s.', get_debug_type($value)));
        }

        return (int) $value;
    }

    public function reorder(CourseVersionId $courseVersionId, array $moduleIds): void
    {
        $ids = array_map(
            static fn (CourseModuleId $moduleId): string => $moduleId->value(),
            $moduleIds,
        );

        $this->reorderRows('course_modules', 'course_version_id', $courseVersionId->value(), $ids);
    }

    private function exists(CourseModuleId $id): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM course_modules WHERE id = :id',
            ['id' => $id->value()],
        );
    }

    /**
     * Rewrites positions in two phases so the unique (parent, position) index
     * is never violated: every row first moves to a unique negative value and
     * only then receives its final position.
     *
     * @param list<string> $ids
     */
    private function reorderRows(string $table, string $parentColumn, string $parentId, array $ids): void
    {
        $ids = array_values($ids);

        if (\count($ids) !== \count(array_unique($ids))) {
            throw new \InvalidArgumentException('The reorder list must not contain duplicate ids.');
        }

        $this->connection->transactional(function (Connection $connection) use ($table, $parentColumn, $parentId, $ids): void {
            $stored = array_map(
                static function (mixed $value): string {
                    if (!\is_string($value)) {
                        throw new \UnexpectedValueException(sprintf('Expected a string id, got %s.', get_debug_type($value)));
                    }

                    return $value;
                },
                $connection->fetchFirstColumn(
                    'SELECT id FROM '.$table.' WHERE '.$parentColumn.' = :parent_id',
                    ['parent_id' => $parentId],
                ),
            );

            $sortedStored = $stored;
            $sortedIds = $ids;
            sort($sortedStored);
            sort($sortedIds);

            if ($sortedStored !== $sortedIds) {
                throw new \InvalidArgumentException(sprintf(
                    'The reorder list must contain exactly the %d children of "%s"; got %d.',
                    \count($stored),
                    $parentId,
                    \count($ids),
                ));
            }

            $connection->executeStatement(
                'UPDATE '.$table.' SET position = -position - 1 WHERE '.$parentColumn.' = :parent_id',
                ['parent_id' => $parentId],
            );

            foreach ($ids as $position => $id) {
                $connection->update($table, ['position' => $position], ['id' => $id]);
            }
        });
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): CourseModule
    {
        return CourseModule::restore(
            CourseModuleId::fromString(DatabaseValue::string($row, 'id')),
            CourseVersionId::fromString(DatabaseValue::string($row, 'course_version_id')),
            DatabaseValue::string($row, 'title'),
            DatabaseValue::nullableString($row, 'description'),
            DatabaseValue::int($row, 'position'),
            1 === DatabaseValue::int($row, 'is_required'),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'created_at')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'updated_at')),
        );
    }
}
