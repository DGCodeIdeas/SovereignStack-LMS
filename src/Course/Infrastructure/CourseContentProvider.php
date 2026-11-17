<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Infrastructure;

use SovereignStack\Lms\Course\Application\Contract\CourseContentProviderInterface;
use SovereignStack\Lms\Course\Application\Contract\PublishedCourseVersion;
use SovereignStack\Lms\Course\Application\Contract\PublishedLesson;
use SovereignStack\Lms\Course\Application\Contract\PublishedModule;
use SovereignStack\Lms\Course\Domain\Entity\CourseVersion;
use SovereignStack\Lms\Course\Domain\Exception\LessonNotFoundException;
use SovereignStack\Lms\Course\Domain\Repository\CourseModuleRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseVersionRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\LessonRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;
use SovereignStack\Lms\Course\Domain\ValueObject\LessonId;

/**
 * Read model exposing only the published course content that learners may see.
 *
 * Enrollment depends on this contract rather than on course repositories so the
 * enrollment application layer never reaches into the course module's domain.
 */
final readonly class CourseContentProvider implements CourseContentProviderInterface
{
    public function __construct(
        private CourseRepositoryInterface $courses,
        private CourseVersionRepositoryInterface $versions,
        private CourseModuleRepositoryInterface $modules,
        private LessonRepositoryInterface $lessons,
    ) {}

    public function publishedVersionForCourse(string $courseId): ?PublishedCourseVersion
    {
        $course = $this->courses->findById($this->courseId($courseId));

        if (null === $course) {
            return null;
        }

        $versionId = $course->currentPublishedVersionId();

        if (null === $versionId || '' === trim($versionId)) {
            return null;
        }

        $version = $this->versions->findById(CourseVersionId::fromString($versionId));

        if (null === $version) {
            return null;
        }

        $modules = $this->modulesForVersion($version);

        return new PublishedCourseVersion(
            $course->id()->value(),
            $version->id()->value(),
            $course->organisationId()->value(),
            $version->title(),
            $modules,
        );
    }

    public function lessonBelongsToVersion(string $lessonId, string $versionId): bool
    {
        return null !== $this->moduleIdForLesson($lessonId, $versionId);
    }

    public function moduleIdForLesson(string $lessonId, string $versionId): ?string
    {
        $lesson = $this->lessons->findById($this->lessonId($lessonId));

        if (null === $lesson) {
            return null;
        }

        $module = $this->modules->findById($lesson->courseModuleId());

        if (null === $module || $module->courseVersionId()->value() !== $versionId) {
            return null;
        }

        return $module->id()->value();
    }

    /**
     * @return list<PublishedModule>
     */
    public function requiredModules(string $versionId): array
    {
        $version = $this->versions->findById($this->versionId($versionId));

        if (null === $version) {
            return [];
        }

        return array_values(array_filter(
            $this->modulesForVersion($version),
            static fn (PublishedModule $module): bool => $module->isRequired,
        ));
    }

    public function lessonNotFound(string $lessonId): LessonNotFoundException
    {
        return LessonNotFoundException::forId($lessonId);
    }

    /**
     * @return list<PublishedModule>
     */
    private function modulesForVersion(CourseVersion $version): array
    {
        $modules = [];

        foreach ($this->modules->listByVersion($version->id()) as $module) {
            $lessons = [];

            foreach ($this->lessons->listByModule($module->id()) as $lesson) {
                $lessons[] = new PublishedLesson($lesson->id()->value(), $lesson->title());
            }

            $modules[] = new PublishedModule(
                $module->id()->value(),
                $module->title(),
                $module->isRequired(),
                $lessons,
            );
        }

        return $modules;
    }

    private function courseId(string $value): CourseId
    {
        return CourseId::fromString($value);
    }

    private function versionId(string $value): CourseVersionId
    {
        return CourseVersionId::fromString($value);
    }

    private function lessonId(string $value): LessonId
    {
        return LessonId::fromString($value);
    }
}
