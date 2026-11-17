<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Contract;

use SovereignStack\Lms\Course\Application\Contract\CourseContentProviderInterface;
use SovereignStack\Lms\Course\Application\Contract\PublishedCourseVersion;
use SovereignStack\Lms\Course\Application\Contract\PublishedModule;
use SovereignStack\Lms\Course\Domain\Exception\LessonNotFoundException;

/**
 * Bridges the published course content contract to the enrollment-owned
 * contract so enrollment handlers never import the course module directly.
 */
final readonly class CourseContentAdapter implements EnrollmentCourseContentInterface
{
    public function __construct(
        private CourseContentProviderInterface $content,
    ) {}

    public function publishedVersionForCourse(string $courseId): ?PublishedCourseVersion
    {
        return $this->content->publishedVersionForCourse($courseId);
    }

    public function versionContent(string $versionId): ?PublishedCourseVersion
    {
        return $this->content->versionContent($versionId);
    }

    public function lessonBelongsToVersion(string $lessonId, string $versionId): bool
    {
        return $this->content->lessonBelongsToVersion($lessonId, $versionId);
    }

    public function moduleIdForLesson(string $lessonId, string $versionId): ?string
    {
        return $this->content->moduleIdForLesson($lessonId, $versionId);
    }

    /**
     * @return list<string>
     */
    public function lessonIdsForModule(string $versionId, string $moduleId): array
    {
        return $this->content->lessonIdsForModule($versionId, $moduleId);
    }

    public function versionLessonContent(string $versionId, string $lessonId): ?string
    {
        return $this->content->versionLessonContent($versionId, $lessonId);
    }

    /**
     * @return list<PublishedModule>
     */
    public function requiredModules(string $versionId): array
    {
        return $this->content->requiredModules($versionId);
    }

    public function lessonNotFound(string $lessonId): LessonNotFoundException
    {
        return $this->content->lessonNotFound($lessonId);
    }
}
