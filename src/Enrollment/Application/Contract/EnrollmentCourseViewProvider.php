<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Contract;

/**
 * Projects the published course content contract into the enrollment
 * presentation view so the controller never imports the course module.
 */
final readonly class EnrollmentCourseViewProvider implements EnrollmentCourseViewProviderInterface
{
    public function __construct(
        private EnrollmentCourseContentInterface $content,
    ) {}

    public function forCourse(string $courseId): ?EnrollmentCourseView
    {
        try {
            $version = $this->content->publishedVersionForCourse($courseId);
        } catch (\InvalidArgumentException) {
            return null;
        }

        if (null === $version) {
            return null;
        }

        $modules = [];

        foreach ($version->modules as $module) {
            $lessons = [];

            foreach ($module->lessons as $lesson) {
                $lessons[] = [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                ];
            }

            $modules[] = [
                'id' => $module->id,
                'title' => $module->title,
                'isRequired' => $module->isRequired,
                'lessons' => $lessons,
            ];
        }

        return new EnrollmentCourseView(
            $version->courseId,
            $version->versionId,
            $version->title,
            $modules,
        );
    }
}
