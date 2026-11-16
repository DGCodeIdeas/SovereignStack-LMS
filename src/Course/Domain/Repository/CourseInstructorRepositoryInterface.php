<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Repository;

use SovereignStack\Lms\Course\Domain\Entity\CourseInstructor;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdInterface;

interface CourseInstructorRepositoryInterface
{
    public function save(CourseInstructor $instructor): void;

    public function findByCourseAndUser(CourseId $courseId, UserIdInterface $userId): ?CourseInstructor;

    /**
     * @return list<CourseInstructor>
     */
    public function listByCourse(CourseId $courseId): array;
}
