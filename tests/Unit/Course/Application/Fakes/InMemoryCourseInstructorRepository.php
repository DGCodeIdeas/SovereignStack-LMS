<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Fakes;

use SovereignStack\Lms\Course\Domain\Entity\CourseInstructor;
use SovereignStack\Lms\Course\Domain\Repository\CourseInstructorRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdInterface;

final class InMemoryCourseInstructorRepository implements CourseInstructorRepositoryInterface
{
    /**
     * @var array<string, CourseInstructor>
     */
    public array $instructors = [];

    public function save(CourseInstructor $instructor): void
    {
        $this->instructors[$instructor->id()->value()] = $instructor;
    }

    public function findByCourseAndUser(CourseId $courseId, UserIdInterface $userId): ?CourseInstructor
    {
        foreach ($this->instructors as $instructor) {
            if ($instructor->courseId()->equals($courseId) && $instructor->userId()->value() === $userId->value()) {
                return $instructor;
            }
        }

        return null;
    }

    public function listByCourse(CourseId $courseId): array
    {
        $result = [];

        foreach ($this->instructors as $instructor) {
            if ($instructor->courseId()->equals($courseId)) {
                $result[] = $instructor;
            }
        }

        return $result;
    }
}
