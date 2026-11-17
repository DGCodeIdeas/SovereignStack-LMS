<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Contract;

interface EnrollmentCourseViewProviderInterface
{
    /**
     * Resolves the current published version of a course. Kept for callers
     * that want to preview what a new enrollment would bind to.
     */
    public function forCourse(string $courseId): ?EnrollmentCourseView;

    /**
     * Resolves one exact course version, which is what an existing enrollment
     * is pinned to. Never falls back to the current published version.
     */
    public function forVersion(string $courseId, string $versionId): ?EnrollmentCourseView;
}
