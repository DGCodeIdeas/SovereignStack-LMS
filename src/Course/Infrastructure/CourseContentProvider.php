<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Infrastructure;

use SovereignStack\Lms\Course\Application\Contract\CourseContentProviderInterface;
use SovereignStack\Lms\Course\Application\Contract\HtmlSanitizerInterface;
use SovereignStack\Lms\Course\Application\Contract\PublishedCourseVersion;
use SovereignStack\Lms\Course\Application\Contract\PublishedLesson;
use SovereignStack\Lms\Course\Application\Contract\PublishedModule;
use SovereignStack\Lms\Course\Domain\Entity\Course;
use SovereignStack\Lms\Course\Domain\Entity\CourseVersion;
use SovereignStack\Lms\Course\Domain\Exception\LessonNotFoundException;
use SovereignStack\Lms\Course\Domain\Repository\CourseModuleRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseVersionRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\LessonContentRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\LessonRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseModuleId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;
use SovereignStack\Lms\Course\Domain\ValueObject\LessonId;

/**
 * Read model exposing only the published course content that learners may see.
 *
 * Enrollment depends on this contract rather than on course repositories so the
 * enrollment application layer never reaches into the course module's domain.
 * Lesson bodies are always re-sanitised on read so legacy or directly imported
 * rows can never leak executable markup to the learner view.
 */
final readonly class CourseContentProvider implements CourseContentProviderInterface
{
    public function __construct(
        private CourseRepositoryInterface $courses,
        private CourseVersionRepositoryInterface $versions,
        private CourseModuleRepositoryInterface $modules,
        private LessonRepositoryInterface $lessons,
        private LessonContentRepositoryInterface $contents,
        private HtmlSanitizerInterface $sanitizer,
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

        return $this->buildVersion($course, $version);
    }

    public function versionContent(string $versionId): ?PublishedCourseVersion
    {
        try {
            $version = $this->versions->findById($this->versionId($versionId));
        } catch (\InvalidArgumentException) {
            return null;
        }

        if (null === $version) {
            return null;
        }

        $course = $this->courses->findById($version->courseId());

        if (null === $course) {
            return null;
        }

        return $this->buildVersion($course, $version);
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
     * @return list<string>
     */
    public function lessonIdsForModule(string $versionId, string $moduleId): array
    {
        try {
            $module = $this->modules->findById(CourseModuleId::fromString($moduleId));
        } catch (\InvalidArgumentException) {
            return [];
        }

        if (null === $module || $module->courseVersionId()->value() !== $versionId) {
            return [];
        }

        $lessonIds = [];

        foreach ($this->lessons->listByModule($module->id()) as $lesson) {
            $lessonIds[] = $lesson->id()->value();
        }

        return $lessonIds;
    }

    public function versionLessonContent(string $versionId, string $lessonId): ?string
    {
        if (!$this->lessonBelongsToVersion($lessonId, $versionId)) {
            return null;
        }

        $content = $this->contents->findByLesson($this->lessonId($lessonId));

        if (null === $content) {
            return null;
        }

        $html = $this->sanitizer->sanitize($content->contentHtml());

        return '' === trim($html) ? null : $html;
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
            $this->modulesForVersion($version, false),
            static fn (PublishedModule $module): bool => $module->isRequired,
        ));
    }

    public function lessonNotFound(string $lessonId): LessonNotFoundException
    {
        return LessonNotFoundException::forId($lessonId);
    }

    private function buildVersion(Course $course, CourseVersion $version): PublishedCourseVersion
    {
        return new PublishedCourseVersion(
            $course->id()->value(),
            $version->id()->value(),
            $course->organisationId()->value(),
            $version->title(),
            $this->modulesForVersion($version, true),
            $course->visibility()->value,
            $version->description(),
        );
    }

    /**
     * @return list<PublishedModule>
     */
    private function modulesForVersion(CourseVersion $version, bool $withContent): array
    {
        $modules = [];

        foreach ($this->modules->listByVersion($version->id()) as $module) {
            $lessons = [];

            foreach ($this->lessons->listByModule($module->id()) as $lesson) {
                $lessons[] = new PublishedLesson(
                    $lesson->id()->value(),
                    $lesson->title(),
                    $withContent ? $this->lessonContent($lesson->id()) : '',
                );
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

    private function lessonContent(LessonId $lessonId): string
    {
        $content = $this->contents->findByLesson($lessonId);

        if (null === $content) {
            return '';
        }

        return $this->sanitizer->sanitize($content->contentHtml());
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
