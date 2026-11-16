<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Repository;

use SovereignStack\Lms\Course\Domain\Entity\CourseModule;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseModuleId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;

interface CourseModuleRepositoryInterface
{
    public function save(CourseModule $module): void;

    public function findById(CourseModuleId $id): ?CourseModule;

    /**
     * @return list<CourseModule>
     */
    public function listByVersion(CourseVersionId $courseVersionId): array;

    public function delete(CourseModuleId $id): void;

    public function nextPosition(CourseVersionId $courseVersionId): int;

    /**
     * Applies the given order to every module of the version. The list must
     * contain exactly the ids currently stored for that version. Positions are
     * rewritten transactionally so the unique (version, position) constraint is
     * never violated, even when the order is reversed.
     *
     * @param list<CourseModuleId> $moduleIds
     */
    public function reorder(CourseVersionId $courseVersionId, array $moduleIds): void;
}
