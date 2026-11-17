<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Contract;

use SovereignStack\Lms\Course\Domain\Exception\LessonNotFoundException as DomainLessonNotFoundException;

interface CourseContentProviderInterface
{
    public function publishedVersionForCourse(string $courseId): ?PublishedCourseVersion;

    /**
     * Loads the content of a specific course version even when it is no longer
     * the current published version, so enrollments stay pinned to the version
     * they were created against.
     */
    public function versionContent(string $versionId): ?PublishedCourseVersion;

    public function lessonBelongsToVersion(string $lessonId, string $versionId): bool;

    public function moduleIdForLesson(string $lessonId, string $versionId): ?string;

    /**
     * Ordered lesson identifiers of a module, empty when the module does not
     * belong to the given version.
     *
     * @return list<string>
     */
    public function lessonIdsForModule(string $versionId, string $moduleId): array;

    /**
     * Sanitised content body for a lesson inside a specific version, or null
     * when the lesson is not part of that version or has no content yet.
     */
    public function versionLessonContent(string $versionId, string $lessonId): ?string;

    /**
     * @return list<PublishedModule>
     */
    public function requiredModules(string $versionId): array;

    public function lessonNotFound(string $lessonId): DomainLessonNotFoundException;
}
