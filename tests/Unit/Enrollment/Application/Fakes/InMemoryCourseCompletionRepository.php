<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Enrollment\Application\Fakes;

use SovereignStack\Lms\Enrollment\Domain\Entity\CourseCompletion;
use SovereignStack\Lms\Enrollment\Domain\Repository\CourseCompletionRepositoryInterface;

final class InMemoryCourseCompletionRepository implements CourseCompletionRepositoryInterface
{
    /**
     * @var array<string, CourseCompletion>
     */
    public array $completions = [];

    public function save(CourseCompletion $completion): void
    {
        $this->completions[$completion->enrollmentId()] = $completion;
    }

    public function findByEnrollment(string $enrollmentId): ?CourseCompletion
    {
        return $this->completions[$enrollmentId] ?? null;
    }

    public function existsForEnrollment(string $enrollmentId): bool
    {
        return isset($this->completions[$enrollmentId]);
    }
}
