<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Enrollment\Application\Fakes;

use SovereignStack\Lms\Course\Application\Contract\PublishedCourseVersion;
use SovereignStack\Lms\Course\Application\Contract\PublishedLesson;
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
        string $visibility = 'organisation_only',
        ?string $description = null,
    ): PublishedCourseVersion {
        $version = new PublishedCourseVersion(
            $courseId,
            $versionId,
            $organisationId,
            $title,
            $modules,
            $visibility,
            $description,
        );
        $this->versions[$courseId] = $version;

        return $version;
    }

    public function publishedVersionForCourse(string $courseId): ?PublishedCourseVersion
    {
        return $this->versions[$courseId] ?? null;
    }

    public function versionContent(string $versionId): ?PublishedCourseVersion
    {
        foreach ($this->versions as $version) {
            if ($version->versionId === $versionId) {
                return $version;
            }
        }

        return null;
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

    /**
     * @return list<string>
     */
    public function lessonIdsForModule(string $versionId, string $moduleId): array
    {
        $version = $this->versionContent($versionId);

        if (null === $version) {
            return [];
        }

        foreach ($version->modules as $module) {
            if ($module->id !== $moduleId) {
                continue;
            }

            return array_map(
                static fn (PublishedLesson $lesson): string => $lesson->id,
                $module->lessons,
            );
        }

        return [];
    }

    public function versionLessonContent(string $versionId, string $lessonId): ?string
    {
        $version = $this->versionContent($versionId);

        if (null === $version) {
            return null;
        }

        foreach ($version->modules as $module) {
            foreach ($module->lessons as $lesson) {
                if ($lesson->id === $lessonId) {
                    return '' === $lesson->contentHtml ? null : $lesson->contentHtml;
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
