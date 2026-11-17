<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Domain\Entity;

use SovereignStack\Lms\Learning\Domain\Enum\ProgressStatus;
use SovereignStack\Lms\Learning\Domain\Exception\ProgressConflictException;
use SovereignStack\Lms\Learning\Domain\ValueObject\ModuleProgressId;

final class ModuleProgress
{
    private const RANK = [
        'not_started' => 0,
        'in_progress' => 1,
        'completed' => 2,
    ];

    private function __construct(
        private ModuleProgressId $id,
        private string $enrollmentId,
        private string $courseModuleId,
        private ProgressStatus $status,
        private ?\DateTimeImmutable $startedAt,
        private ?\DateTimeImmutable $completedAt,
        private \DateTimeImmutable $updatedAt,
        private int $version,
    ) {}

    public static function create(
        ModuleProgressId $id,
        string $enrollmentId,
        string $courseModuleId,
        \DateTimeImmutable $now,
    ): self {
        return new self($id, $enrollmentId, $courseModuleId, ProgressStatus::NOT_STARTED, null, null, $now, 1);
    }

    public static function restore(
        ModuleProgressId $id,
        string $enrollmentId,
        string $courseModuleId,
        ProgressStatus $status,
        ?\DateTimeImmutable $startedAt,
        ?\DateTimeImmutable $completedAt,
        \DateTimeImmutable $updatedAt,
        int $version,
    ): self {
        if ($version < 1) {
            throw new \InvalidArgumentException('Version must be at least 1.');
        }

        return new self($id, $enrollmentId, $courseModuleId, $status, $startedAt, $completedAt, $updatedAt, $version);
    }

    public function id(): ModuleProgressId
    {
        return $this->id;
    }

    public function enrollmentId(): string
    {
        return $this->enrollmentId;
    }

    public function courseModuleId(): string
    {
        return $this->courseModuleId;
    }

    public function status(): ProgressStatus
    {
        return $this->status;
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

    public function incrementVersion(): void
    {
        ++$this->version;
    }
}
