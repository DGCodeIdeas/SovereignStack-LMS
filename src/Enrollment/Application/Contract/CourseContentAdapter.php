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

    public function lessonBelongsToVersion(string $lessonId, string $versionId): bool
    {
        return $this->content->lessonBelongsToVersion($lessonId, $versionId);
    }

    public function moduleIdForLesson(string $lessonId, string $versionId): ?string
    {
        return $this->content->moduleIdForLesson($lessonId, $versionId);
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
