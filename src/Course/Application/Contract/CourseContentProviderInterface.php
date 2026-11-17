<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Contract;

use SovereignStack\Lms\Course\Domain\Exception\LessonNotFoundException as DomainLessonNotFoundException;

interface CourseContentProviderInterface
{
    public function publishedVersionForCourse(string $courseId): ?PublishedCourseVersion;

    public function lessonBelongsToVersion(string $lessonId, string $versionId): bool;

    public function moduleIdForLesson(string $lessonId, string $versionId): ?string;

    /**
     * @return list<PublishedModule>
     */
    public function requiredModules(string $versionId): array;

    public function lessonNotFound(string $lessonId): DomainLessonNotFoundException;
}
