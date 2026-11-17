<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Contract;

interface EnrollmentCourseViewProviderInterface
{
    public function forCourse(string $courseId): ?EnrollmentCourseView;
}
