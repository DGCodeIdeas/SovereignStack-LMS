<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Service;

use SovereignStack\Lms\Course\Application\Contract\CourseContentProviderInterface;
use SovereignStack\Lms\Course\Application\Contract\LearnerCourseCatalogInterface;
use SovereignStack\Lms\Course\Application\Contract\PublishedCourseVersion;
use SovereignStack\Lms\Course\Application\Contract\PublishedModule;
use SovereignStack\Lms\Course\Domain\Enum\CourseVisibility;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;

/**
 * Learner-facing catalogue read model.
 *
 * Visibility is enforced here as well as in the repository query so callers
 * cannot accidentally expose organisation-only, restricted or archived
 * courses by using a different repository implementation.
 */
final readonly class LearnerCourseCatalog implements LearnerCourseCatalogInterface
{
    public function __construct(
        private CourseRepositoryInterface $courses,
        private CourseContentProviderInterface $content,
    ) {}

    public function list(string $userId, array $organisationIds): array
    {
        $allowedOrganisations = array_fill_keys($organisationIds, true);
        $courses = [];

        foreach ($this->courses->listPublishedForLearner($organisationIds) as $course) {
            if (!$this->isVisible($course['visibility'], $course['organisationId'], $allowedOrganisations)) {
                continue;
            }

            $courses[] = [
                'id' => $course['id'],
                'slug' => $course['slug'],
                'title' => $course['title'],
                'description' => $course['description'],
                'organisationId' => $course['organisationId'],
                'visibility' => $course['visibility'],
                'moduleCount' => $course['moduleCount'],
            ];
        }

        return $courses;
    }

    public function view(string $courseId, array $organisationIds): ?array
    {
        try {
            $course = $this->courses->findById(CourseId::fromString($courseId));
        } catch (\InvalidArgumentException) {
            return null;
        }

        if (null === $course) {
            return null;
        }

        $organisationId = $course->organisationId()->value();
        $visibility = $course->visibility()->value;

        if (!$this->isVisible($visibility, $organisationId, array_fill_keys($organisationIds, true))) {
            return null;
        }

        $version = $this->content->publishedVersionForCourse($courseId);

        if (null === $version) {
            return null;
        }

        return [
            'courseId' => $version->courseId,
            'title' => $version->title,
            'description' => $version->description,
            'modules' => $this->modules($version),
        ];
    }

    /**
     * @param array<string, true> $allowedOrganisations
     */
    private function isVisible(string $visibility, string $organisationId, array $allowedOrganisations): bool
    {
        if (CourseVisibility::PUBLIC->value === $visibility) {
            return true;
        }

        if (CourseVisibility::ORGANISATION_ONLY->value === $visibility) {
            return isset($allowedOrganisations[$organisationId]);
        }

        return false;
    }

    /**
     * @return list<array{
     *     id: string,
     *     title: string,
     *     isRequired: bool,
     *     lessons: list<array{id: string, title: string}>
     * }>
     */
    private function modules(PublishedCourseVersion $version): array
    {
        $modules = [];

        foreach ($version->modules as $module) {
            $modules[] = [
                'id' => $module->id,
                'title' => $module->title,
                'isRequired' => $module->isRequired,
                'lessons' => $this->lessons($module),
            ];
        }

        return $modules;
    }

    /**
     * @return list<array{id: string, title: string}>
     */
    private function lessons(PublishedModule $module): array
    {
        $lessons = [];

        foreach ($module->lessons as $lesson) {
            $lessons[] = [
                'id' => $lesson->id,
                'title' => $lesson->title,
            ];
        }

        return $lessons;
    }
}
