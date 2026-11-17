<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Domain\Repository;

use SovereignStack\Lms\Learning\Domain\Entity\ModuleProgress;
use SovereignStack\Lms\Learning\Domain\ValueObject\ModuleProgressId;

interface ModuleProgressRepositoryInterface
{
    public function save(ModuleProgress $progress): void;

    public function findById(ModuleProgressId $id): ?ModuleProgress;

    public function findByEnrollmentAndModule(string $enrollmentId, string $courseModuleId): ?ModuleProgress;

    public function findForUpdate(string $enrollmentId, string $courseModuleId): ?ModuleProgress;

    /**
     * @return list<ModuleProgress>
     */
    public function listByEnrollment(string $enrollmentId): array;
}
