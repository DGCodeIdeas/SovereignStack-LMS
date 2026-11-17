<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Enrollment\Application\Fakes;

use SovereignStack\Lms\Learning\Domain\Entity\ModuleProgress;
use SovereignStack\Lms\Learning\Domain\Repository\ModuleProgressRepositoryInterface;
use SovereignStack\Lms\Learning\Domain\ValueObject\ModuleProgressId;

final class InMemoryModuleProgressRepository implements ModuleProgressRepositoryInterface
{
    /**
     * @var array<string, ModuleProgress>
     */
    public array $progress = [];

    public int $saveCount = 0;

    public function save(ModuleProgress $progress): void
    {
        ++$this->saveCount;
        $this->progress[$progress->id()->value()] = $progress;
    }

    public function findById(ModuleProgressId $id): ?ModuleProgress
    {
        return $this->progress[$id->value()] ?? null;
    }

    public function findByEnrollmentAndModule(string $enrollmentId, string $courseModuleId): ?ModuleProgress
    {
        foreach ($this->progress as $progress) {
            if ($progress->enrollmentId() === $enrollmentId && $progress->courseModuleId() === $courseModuleId) {
                return $progress;
            }
        }

        return null;
    }

    public function findForUpdate(string $enrollmentId, string $courseModuleId): ?ModuleProgress
    {
        return $this->findByEnrollmentAndModule($enrollmentId, $courseModuleId);
    }

    public function listByEnrollment(string $enrollmentId): array
    {
        return array_values(array_filter(
            $this->progress,
            static fn (ModuleProgress $progress): bool => $progress->enrollmentId() === $enrollmentId,
        ));
    }
}
