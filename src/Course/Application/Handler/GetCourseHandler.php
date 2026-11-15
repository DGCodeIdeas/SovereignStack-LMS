<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Query\GetCourseQuery;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Course\Application\Support\CourseVersionResolver;
use SovereignStack\Lms\Course\Application\Support\Identifiers;
use SovereignStack\Lms\Course\Domain\Entity\Course;
use SovereignStack\Lms\Course\Domain\Entity\CourseModule;
use SovereignStack\Lms\Course\Domain\Entity\CourseVersion;
use SovereignStack\Lms\Course\Domain\Entity\Lesson;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotFoundException;
use SovereignStack\Lms\Course\Domain\Repository\CourseModuleRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseVersionRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\LessonContentRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\LessonRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;

final readonly class GetCourseHandler
{
    public function __construct(
        private CourseAccessPolicy $policy,
        private CourseVersionResolver $versionResolver,
        private CourseRepositoryInterface $courses,
        private CourseVersionRepositoryInterface $versions,
        private CourseModuleRepositoryInterface $modules,
        private LessonRepositoryInterface $lessons,
        private LessonContentRepositoryInterface $contents,
    ) {}

    /**
     * @return array{
     *     id: string,
     *     organisationId: string,
     *     ownerUserId: string,
     *     slug: string,
     *     visibility: string,
     *     currentPublishedVersionId: null|string,
     *     version: null|array{
     *         id: string,
     *         versionNumber: int,
     *         title: string,
     *         description: null|string,
     *         status: string,
     *         modules: list<array{
     *             id: string,
     *             title: string,
     *             description: null|string,
     *             position: int,
     *             isRequired: bool,
     *             lessons: list<array{
     *                 id: string,
     *                 title: string,
     *                 position: int,
     *                 content: null|array{html: string, metadata: array<string, mixed>}
     *             }>
     *         }>
     *     }
     * }
     */
    public function __invoke(GetCourseQuery $query): array
    {
        $courseId = Identifiers::courseId($query->courseId);
        $course = $this->courses->findById($courseId) ?? throw CourseNotFoundException::forId($query->courseId);

        $this->policy->requireView($query->actorUserId, $course);

        $version = $this->editableVersion($course);

        return [
            'id' => $course->id()->value(),
            'organisationId' => $course->organisationId()->value(),
            'ownerUserId' => $course->ownerUserId()->value(),
            'slug' => $course->slug(),
            'visibility' => $course->visibility()->value,
            'currentPublishedVersionId' => $course->currentPublishedVersionId(),
            'version' => null === $version ? null : $this->versionPayload($version),
        ];
    }

    private function editableVersion(Course $course): ?CourseVersion
    {
        $draft = $this->versions->findDraftByCourse($course->id());

        if (null !== $draft) {
            return $draft;
        }

        $publishedId = $course->currentPublishedVersionId();

        if (null !== $publishedId) {
            $published = $this->versions->findById(CourseVersionId::fromString($publishedId));

            if (null !== $published) {
                return $published;
            }
        }

        return $this->versionResolver->latest($course->id());
    }

    /**
     * @return array{
     *     id: string,
     *     versionNumber: int,
     *     title: string,
     *     description: null|string,
     *     status: string,
     *     modules: list<array{
     *         id: string,
     *         title: string,
     *         description: null|string,
     *         position: int,
     *         isRequired: bool,
     *         lessons: list<array{
     *             id: string,
     *             title: string,
     *             position: int,
     *             content: null|array{html: string, metadata: array<string, mixed>}
     *         }>
     *     }>
     * }
     */
    private function versionPayload(CourseVersion $version): array
    {
        return [
            'id' => $version->id()->value(),
            'versionNumber' => $version->versionNumber(),
            'title' => $version->title(),
            'description' => $version->description(),
            'status' => $version->status()->value,
            'modules' => array_map(
                fn (CourseModule $module): array => [
                    'id' => $module->id()->value(),
                    'title' => $module->title(),
                    'description' => $module->description(),
                    'position' => $module->position(),
                    'isRequired' => $module->isRequired(),
                    'lessons' => array_map(
                        fn (Lesson $lesson): array => [
                            'id' => $lesson->id()->value(),
                            'title' => $lesson->title(),
                            'position' => $lesson->position(),
                            'content' => $this->contentPayload($lesson),
                        ],
                        $this->lessons->listByModule($module->id()),
                    ),
                ],
                $this->modules->listByVersion($version->id()),
            ),
        ];
    }

    /**
     * @return null|array{html: string, metadata: array<string, mixed>}
     */
    private function contentPayload(Lesson $lesson): ?array
    {
        $content = $this->contents->findByLesson($lesson->id());

        if (null === $content) {
            return null;
        }

        return ['html' => $content->contentHtml(), 'metadata' => $content->metadata()];
    }
}
