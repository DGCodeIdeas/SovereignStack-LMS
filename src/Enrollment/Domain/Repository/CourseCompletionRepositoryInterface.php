<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Domain\Repository;

use SovereignStack\Lms\Enrollment\Domain\Entity\CourseCompletion;

interface CourseCompletionRepositoryInterface
{
    public function save(CourseCompletion $completion): void;

    public function findByEnrollment(string $enrollmentId): ?CourseCompletion;

    public function existsForEnrollment(string $enrollmentId): bool;
}
