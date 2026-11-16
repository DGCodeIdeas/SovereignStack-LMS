<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Repository;

use SovereignStack\Lms\Course\Domain\Entity\CourseVersion;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;

interface CourseVersionRepositoryInterface
{
    public function save(CourseVersion $version): void;

    public function findById(CourseVersionId $id): ?CourseVersion;

    public function findDraftByCourse(CourseId $courseId): ?CourseVersion;

    public function nextVersionNumber(CourseId $courseId): int;

    /**
     * @return list<CourseVersion>
     */
    public function listByCourse(CourseId $courseId): array;
}
