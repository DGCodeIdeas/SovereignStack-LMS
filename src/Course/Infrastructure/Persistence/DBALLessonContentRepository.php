<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use SovereignStack\Lms\Course\Domain\Entity\LessonContent;
use SovereignStack\Lms\Course\Domain\Repository\LessonContentRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\LessonId;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DatabaseValue;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DateTimeCodec;

final readonly class DBALLessonContentRepository implements LessonContentRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function save(LessonContent $content): void
    {
        $data = [
            'content_html' => $content->contentHtml(),
            'content_metadata' => DatabaseValue::encodeJson($content->metadata()),
            'updated_at' => DateTimeCodec::toDatabase($content->updatedAt()),
        ];

        if ($this->exists($content->id())) {
            $this->connection->update('lesson_contents', $data, ['id' => $content->id()]);

            return;
        }

        $this->connection->insert('lesson_contents', $data + [
            'id' => $content->id(),
            'lesson_id' => $content->lessonId()->value(),
            'created_at' => DateTimeCodec::toDatabase($content->createdAt()),
        ]);
    }

    public function findByLesson(LessonId $lessonId): ?LessonContent
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM lesson_contents WHERE lesson_id = :lesson_id',
            ['lesson_id' => $lessonId->value()],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    private function exists(string $id): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM lesson_contents WHERE id = :id',
            ['id' => $id],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): LessonContent
    {
        return LessonContent::restore(
            DatabaseValue::string($row, 'id'),
            LessonId::fromString(DatabaseValue::string($row, 'lesson_id')),
            DatabaseValue::string($row, 'content_html'),
            DatabaseValue::decodeJson(DatabaseValue::nullableString($row, 'content_metadata')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'created_at')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'updated_at')),
        );
    }
}
