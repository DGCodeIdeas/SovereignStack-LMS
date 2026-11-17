<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Domain\Entity;

use SovereignStack\Lms\Learning\Domain\Enum\ProgressStatus;
use SovereignStack\Lms\Learning\Domain\Exception\ProgressConflictException;
use SovereignStack\Lms\Learning\Domain\ValueObject\LessonProgressId;

final class LessonProgress
{
    private const RANK = [
        'not_started' => 0,
        'in_progress' => 1,
        'completed' => 2,
    ];

    /**
     * @param array<string, mixed> $progressData
     */
    private function __construct(
        private LessonProgressId $id,
        private string $enrollmentId,
        private string $lessonId,
        private ProgressStatus $status,
        private array $progressData,
        private ?\DateTimeImmutable $startedAt,
        private ?\DateTimeImmutable $completedAt,
        private \DateTimeImmutable $updatedAt,
        private int $version,
    ) {}

    public static function create(
        LessonProgressId $id,
        string $enrollmentId,
        string $lessonId,
        \DateTimeImmutable $now,
    ): self {
        return new self($id, $enrollmentId, $lessonId, ProgressStatus::NOT_STARTED, [], null, null, $now, 1);
    }

    /**
     * @param array<string, mixed> $progressData
     */
    public static function restore(
        LessonProgressId $id,
        string $enrollmentId,
        string $lessonId,
        ProgressStatus $status,
        array $progressData,
        ?\DateTimeImmutable $startedAt,
        ?\DateTimeImmutable $completedAt,
        \DateTimeImmutable $updatedAt,
        int $version,
    ): self {
        if ($version < 1) {
            throw new \InvalidArgumentException('Version must be at least 1.');
        }

        return new self($id, $enrollmentId, $lessonId, $status, $progressData, $startedAt, $completedAt, $updatedAt, $version);
    }

    public function id(): LessonProgressId
    {
        return $this->id;
    }

    public function enrollmentId(): string
    {
        return $this->enrollmentId;
    }

    public function lessonId(): string
    {
        return $this->lessonId;
    }

    public function status(): ProgressStatus
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function progressData(): array
    {
        return $this->progressData;
    }

    public function startedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function markStarted(\DateTimeImmutable $now): void
    {
        $this->transitionTo(ProgressStatus::IN_PROGRESS, $now);
    }

    public function markCompleted(\DateTimeImmutable $now): void
    {
        $this->transitionTo(ProgressStatus::COMPLETED, $now);
    }

    public function transitionTo(ProgressStatus $target, \DateTimeImmutable $now): void
    {
        if ($target === $this->status) {
            return;
        }

        if (self::RANK[$target->value] < self::RANK[$this->status->value]) {
            throw ProgressConflictException::backwardsTransition($this->status->value, $target->value);
        }

        $this->status = $target;
        $this->startedAt ??= $now;
        $this->completedAt = ProgressStatus::COMPLETED === $target ? $now : null;
        $this->updatedAt = $now;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function replaceProgressData(array $data, \DateTimeImmutable $now): void
    {
        $this->progressData = $data;
        $this->updatedAt = $now;
    }

    public function incrementVersion(): void
    {
        ++$this->version;
    }
}
