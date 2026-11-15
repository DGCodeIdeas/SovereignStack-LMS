<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use SovereignStack\Lms\Course\Domain\Entity\Lesson;
use SovereignStack\Lms\Course\Domain\Repository\LessonRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseModuleId;
use SovereignStack\Lms\Course\Domain\ValueObject\LessonId;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DatabaseValue;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DateTimeCodec;

final readonly class DBALLessonRepository implements LessonRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function save(Lesson $lesson): void
    {
        $data = [
            'title' => $lesson->title(),
            'position' => $lesson->position(),
            'updated_at' => DateTimeCodec::toDatabase($lesson->updatedAt()),
        ];

        if ($this->exists($lesson->id())) {
            $this->connection->update('lessons', $data, ['id' => $lesson->id()->value()]);

            return;
        }

        $this->connection->insert('lessons', $data + [
            'id' => $lesson->id()->value(),
            'course_module_id' => $lesson->courseModuleId()->value(),
            'created_at' => DateTimeCodec::toDatabase($lesson->createdAt()),
        ]);
    }

    public function findById(LessonId $id): ?Lesson
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM lessons WHERE id = :id',
            ['id' => $id->value()],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function listByModule(CourseModuleId $courseModuleId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM lessons WHERE course_module_id = :course_module_id ORDER BY position',
            ['course_module_id' => $courseModuleId->value()],
        );

        return array_map(fn (array $row): Lesson => $this->hydrate($row), $rows);
    }

    public function delete(LessonId $id): void
    {
        $this->connection->executeStatement(
            'DELETE FROM lessons WHERE id = :id',
            ['id' => $id->value()],
        );
    }

    public function nextPosition(CourseModuleId $courseModuleId): int
    {
        $value = $this->connection->fetchOne(
            'SELECT COALESCE(MAX(position), -1) + 1 FROM lessons WHERE course_module_id = :course_module_id',
            ['course_module_id' => $courseModuleId->value()],
        );

        if (!is_numeric($value)) {
            throw new \UnexpectedValueException(sprintf('Expected a numeric next position, got %s.', get_debug_type($value)));
        }

        return (int) $value;
    }

    public function reorder(CourseModuleId $courseModuleId, array $lessonIds): void
    {
        $ids = array_map(
            static fn (LessonId $lessonId): string => $lessonId->value(),
            $lessonIds,
        );

        $this->reorderRows('lessons', 'course_module_id', $courseModuleId->value(), $ids);
    }

    private function exists(LessonId $id): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM lessons WHERE id = :id',
            ['id' => $id->value()],
        );
    }

    /**
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
    private function hydrate(array $row): Lesson
    {
        return Lesson::restore(
            LessonId::fromString(DatabaseValue::string($row, 'id')),
            CourseModuleId::fromString(DatabaseValue::string($row, 'course_module_id')),
            DatabaseValue::string($row, 'title'),
            DatabaseValue::int($row, 'position'),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'created_at')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'updated_at')),
        );
    }
}
