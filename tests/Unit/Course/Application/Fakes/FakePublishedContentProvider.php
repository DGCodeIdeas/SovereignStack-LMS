<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Fakes;

use SovereignStack\Lms\Course\Application\Contract\CourseContentProviderInterface;
use SovereignStack\Lms\Course\Application\Contract\PublishedCourseVersion;
use SovereignStack\Lms\Course\Application\Contract\PublishedLesson;
use SovereignStack\Lms\Course\Application\Contract\PublishedModule;
use SovereignStack\Lms\Course\Domain\Exception\LessonNotFoundException;

final class FakePublishedContentProvider implements CourseContentProviderInterface
{
    /**
     * @var array<string, PublishedCourseVersion>
     */
    public array $versions = [];

    public function publish(PublishedCourseVersion $version): void
    {
        $this->versions[$version->courseId] = $version;
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

    public function requiredModules(string $versionId): array
    {
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

    public function lessonNotFound(string $lessonId): LessonNotFoundException
    {
        return LessonNotFoundException::forId($lessonId);
    }
}
