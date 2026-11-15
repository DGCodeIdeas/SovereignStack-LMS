<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Entity;

use SovereignStack\Lms\Course\Domain\ValueObject\LessonId;

final class LessonContent
{
    /**
     * @param array<string, mixed> $metadata
     */
    private function __construct(
        private string $id,
        private LessonId $lessonId,
        private string $contentHtml,
        private array $metadata,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @param array<string, mixed> $metadata
     */
    public static function create(
        string $id,
        LessonId $lessonId,
        string $contentHtml,
        array $metadata,
        \DateTimeImmutable $now,
    ): self {
        return new self($id, $lessonId, $contentHtml, $metadata, $now, $now);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function restore(
        string $id,
        LessonId $lessonId,
        string $contentHtml,
        array $metadata,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self($id, $lessonId, $contentHtml, $metadata, $createdAt, $updatedAt);
    }

    /**
     * Sanitisation of the HTML belongs to the application layer; the domain
     * only stores the already-sanitised payload and its metadata.
     *
     * @param array<string, mixed> $metadata
     */
    public function replaceContent(string $contentHtml, array $metadata, \DateTimeImmutable $now): void
    {
        $this->contentHtml = $contentHtml;
        $this->metadata = $metadata;
        $this->updatedAt = $now;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function lessonId(): LessonId
    {
        return $this->lessonId;
    }

    public function contentHtml(): string
    {
        return $this->contentHtml;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
