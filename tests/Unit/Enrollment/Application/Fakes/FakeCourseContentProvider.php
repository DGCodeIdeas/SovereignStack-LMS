<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Enrollment\Application\Fakes;

use SovereignStack\Lms\Course\Application\Contract\PublishedCourseVersion;
use SovereignStack\Lms\Course\Application\Contract\PublishedModule;
use SovereignStack\Lms\Course\Domain\Exception\LessonNotFoundException;
use SovereignStack\Lms\Enrollment\Application\Contract\EnrollmentCourseContentInterface;

final class FakeCourseContentProvider implements EnrollmentCourseContentInterface
{
    /**
     * @var array<string, PublishedCourseVersion>
     */
    public array $versions = [];

    /**
     * @var list<string>
     */
    public array $requestedVersions = [];

    /**
     * @param list<PublishedModule> $modules
     */
    public function publish(
        string $courseId,
        string $versionId,
        string $organisationId,
        string $title,
        array $modules,
    ): PublishedCourseVersion {
        $version = new PublishedCourseVersion($courseId, $versionId, $organisationId, $title, $modules);
        $this->versions[$courseId] = $version;

        return $version;
    }

    public function publishedVersionForCourse(string $courseId): ?PublishedCourseVersion
    {
        return $this->versions[$courseId] ?? null;
    }

    public function lessonBelongsToVersion(string $lessonId, string $versionId): bool
    {
        return null !== $this->moduleIdForLesson($lessonId, $versionId);
    }

    public function moduleIdForLesson(string $lessonId, string $versionId): ?string
    {
        foreach ($this->versions as $version) {
            if ($version->versionId !== $versionId) {
                continue;
            }

            foreach ($version->modules as $module) {
                foreach ($module->lessons as $lesson) {
                    if ($lesson->id === $lessonId) {
                        return $module->id;
                    }
                }
            }
        }

        return null;
    }

    public function lessonNotFound(string $lessonId): LessonNotFoundException
    {
        return LessonNotFoundException::forId($lessonId);
    }

    public function requiredModules(string $versionId): array
    {
        $this->requestedVersions[] = $versionId;

        foreach ($this->versions as $version) {
            if ($version->versionId !== $versionId) {
                continue;
            }

            return array_values(array_filter(
                $version->modules,
                static fn (PublishedModule $module): bool => $module->isRequired,
            ));
        }

        return [];
    }
}
