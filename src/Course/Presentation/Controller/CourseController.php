<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Presentation\Controller;

use SovereignStack\Lms\Course\Application\Command\AddLessonCommand;
use SovereignStack\Lms\Course\Application\Command\AddModuleCommand;
use SovereignStack\Lms\Course\Application\Command\ArchiveCourseCommand;
use SovereignStack\Lms\Course\Application\Command\CreateCourseCommand;
use SovereignStack\Lms\Course\Application\Command\CreateDraftVersionCommand;
use SovereignStack\Lms\Course\Application\Command\PublishCourseCommand;
use SovereignStack\Lms\Course\Application\Command\RejectReviewCommand;
use SovereignStack\Lms\Course\Application\Command\RemoveLessonCommand;
use SovereignStack\Lms\Course\Application\Command\RemoveModuleCommand;
use SovereignStack\Lms\Course\Application\Command\ReorderLessonsCommand;
use SovereignStack\Lms\Course\Application\Command\ReorderModulesCommand;
use SovereignStack\Lms\Course\Application\Command\SaveLessonContentCommand;
use SovereignStack\Lms\Course\Application\Command\SubmitForReviewCommand;
use SovereignStack\Lms\Course\Application\Command\UpdateCourseCommand;
use SovereignStack\Lms\Course\Application\Contract\UserOrganisationsInterface;
use SovereignStack\Lms\Course\Application\Handler\AddLessonHandler;
use SovereignStack\Lms\Course\Application\Handler\AddModuleHandler;
use SovereignStack\Lms\Course\Application\Handler\ArchiveCourseHandler;
use SovereignStack\Lms\Course\Application\Handler\CreateCourseHandler;
use SovereignStack\Lms\Course\Application\Handler\CreateDraftVersionHandler;
use SovereignStack\Lms\Course\Application\Handler\GetCourseHandler;
use SovereignStack\Lms\Course\Application\Handler\ListCoursesHandler;
use SovereignStack\Lms\Course\Application\Handler\PublishCourseHandler;
use SovereignStack\Lms\Course\Application\Handler\RejectReviewHandler;
use SovereignStack\Lms\Course\Application\Handler\RemoveLessonHandler;
use SovereignStack\Lms\Course\Application\Handler\RemoveModuleHandler;
use SovereignStack\Lms\Course\Application\Handler\ReorderLessonsHandler;
use SovereignStack\Lms\Course\Application\Handler\ReorderModulesHandler;
use SovereignStack\Lms\Course\Application\Handler\SaveLessonContentHandler;
use SovereignStack\Lms\Course\Application\Handler\SubmitForReviewHandler;
use SovereignStack\Lms\Course\Application\Handler\UpdateCourseHandler;
use SovereignStack\Lms\Course\Application\Query\GetCourseQuery;
use SovereignStack\Lms\Course\Application\Query\ListCoursesQuery;
use SovereignStack\Lms\Shared\Application\Exception\ConflictException;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Shared\Application\Exception\NotFoundException;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Domain\Exception\DomainException;
use SovereignStack\Lms\Shared\Domain\Security\DomainUserAwareInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CourseController extends AbstractController
{
    private const COURSE_ID_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly UserOrganisationsInterface $organisations,
        private readonly ListCoursesHandler $listCourses,
        private readonly GetCourseHandler $getCourse,
        private readonly CreateCourseHandler $createCourse,
        private readonly UpdateCourseHandler $updateCourse,
        private readonly AddModuleHandler $addModule,
        private readonly RemoveModuleHandler $removeModule,
        private readonly ReorderModulesHandler $reorderModules,
        private readonly AddLessonHandler $addLesson,
        private readonly RemoveLessonHandler $removeLesson,
        private readonly ReorderLessonsHandler $reorderLessons,
        private readonly SaveLessonContentHandler $saveLessonContent,
        private readonly SubmitForReviewHandler $submitForReview,
        private readonly RejectReviewHandler $rejectReview,
        private readonly PublishCourseHandler $publishCourse,
        private readonly ArchiveCourseHandler $archiveCourse,
        private readonly CreateDraftVersionHandler $createDraftVersion,
    ) {}

    #[Route('/courses', name: 'app_courses', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('course/index.html.twig', [
            'courses' => $this->listVisibleCourses($this->actorId()),
        ]);
    }

    #[Route('/courses/new', name: 'app_course_new', methods: ['GET'])]
    public function newCourse(): Response
    {
        return $this->render('course/new.html.twig', [
            'organisations' => $this->organisations->forUser($this->actorId()),
        ]);
    }

    #[Route('/courses', name: 'app_course_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if (null !== $invalid = $this->invalidCsrf($request, 'create_course')) {
            return $invalid;
        }

        try {
            $courseId = ($this->createCourse)(new CreateCourseCommand(
                $this->actorId(),
                $request->request->getString('organisation_id'),
                $request->request->getString('title'),
                $request->request->getString('description'),
                $request->request->getString('slug'),
            ));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'app_course_new');
        }

        $this->addFlash('success', 'Course created as a draft version.');

        return $this->redirectToRoute('app_course_edit', ['courseId' => $courseId->value()]);
    }

    #[Route(
        '/courses/{courseId}',
        name: 'app_course_edit',
        methods: ['GET'],
        requirements: ['courseId' => self::COURSE_ID_REQUIREMENT],
    )]
    public function edit(string $courseId): Response
    {
        $course = $this->courseForEditing($this->actorId(), $courseId);

        $modules = $course['version']['modules'] ?? [];
        $moduleIds = array_map(
            static fn (array $module): string => (string) $module['id'],
            $modules,
        );
        $moduleMoveIds = [];
        $lessonMoveIds = [];

        foreach ($modules as $moduleIndex => $module) {
            $moduleId = (string) $module['id'];
            $moduleMoveIds[$moduleId] = [
                'up' => $this->moved($moduleIds, $moduleIndex, -1),
                'down' => $this->moved($moduleIds, $moduleIndex, 1),
            ];

            $lessons = $module['lessons'];
            $lessonIds = array_map(
                static fn (array $lesson): string => (string) $lesson['id'],
                $lessons,
            );

            foreach ($lessons as $lessonIndex => $lesson) {
                $lessonId = (string) $lesson['id'];
                $lessonMoveIds[$moduleId][$lessonId] = [
                    'up' => $this->moved($lessonIds, $lessonIndex, -1),
                    'down' => $this->moved($lessonIds, $lessonIndex, 1),
                ];
            }
        }

        return $this->render('course/edit.html.twig', [
            'course' => $course,
            'moduleMoveIds' => $moduleMoveIds,
            'lessonMoveIds' => $lessonMoveIds,
        ]);
    }

    #[Route(
        '/courses/{courseId}',
        name: 'app_course_update',
        methods: ['POST'],
        requirements: ['courseId' => self::COURSE_ID_REQUIREMENT],
    )]
    public function update(Request $request, string $courseId): Response
    {
        if (null !== $invalid = $this->invalidCsrf($request, 'update_course')) {
            return $invalid;
        }

        try {
            ($this->updateCourse)(new UpdateCourseCommand(
                $this->actorId(),
                $courseId,
                $request->request->getString('title'),
                $request->request->getString('description'),
                $request->request->getString('slug'),
                $request->request->getString('visibility'),
            ));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'app_course_edit', ['courseId' => $courseId]);
        }

        $this->addFlash('success', 'Course details saved.');

        return $this->redirectToRoute('app_course_edit', ['courseId' => $courseId]);
    }

    #[Route(
        '/courses/{courseId}/modules',
        name: 'app_course_module_add',
        methods: ['POST'],
        requirements: ['courseId' => self::COURSE_ID_REQUIREMENT],
    )]
    public function addModule(Request $request, string $courseId): Response
    {
        if (null !== $invalid = $this->invalidCsrf($request, 'add_module')) {
            return $invalid;
        }

        try {
            ($this->addModule)(new AddModuleCommand(
                $this->actorId(),
                $courseId,
                $request->request->getString('title'),
                $request->request->getString('description'),
                $request->request->has('is_required'),
            ));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'app_course_edit', ['courseId' => $courseId]);
        }

        $this->addFlash('success', 'Module added.');

        return $this->redirectToRoute('app_course_edit', ['courseId' => $courseId]);
    }

    #[Route(
        '/courses/{courseId}/modules/{moduleId}/delete',
        name: 'app_course_module_remove',
        methods: ['POST'],
        requirements: ['courseId' => self::COURSE_ID_REQUIREMENT, 'moduleId' => self::COURSE_ID_REQUIREMENT],
    )]
    public function removeModule(Request $request, string $courseId, string $moduleId): Response
    {
        if (null !== $invalid = $this->invalidCsrf($request, 'remove_module')) {
            return $invalid;
        }

        try {
            ($this->removeModule)(new RemoveModuleCommand($this->actorId(), $courseId, $moduleId));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'app_course_edit', ['courseId' => $courseId]);
        }

        $this->addFlash('success', 'Module removed.');

        return $this->redirectToRoute('app_course_edit', ['courseId' => $courseId]);
    }

    #[Route(
        '/courses/{courseId}/modules/reorder',
        name: 'app_course_modules_reorder',
        methods: ['POST'],
        requirements: ['courseId' => self::COURSE_ID_REQUIREMENT],
    )]
    public function reorderModules(Request $request, string $courseId): Response
    {
        if (null !== $invalid = $this->invalidCsrf($request, 'reorder_modules')) {
            return $invalid;
        }

        try {
            ($this->reorderModules)(new ReorderModulesCommand(
                $this->actorId(),
                $courseId,
                $this->idList($request, 'module_ids'),
            ));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'app_course_edit', ['courseId' => $courseId]);
        }

        $this->addFlash('success', 'Module order saved.');

        return $this->redirectToRoute('app_course_edit', ['courseId' => $courseId]);
    }

    #[Route(
        '/courses/{courseId}/modules/{moduleId}/lessons',
        name: 'app_course_lesson_add',
        methods: ['POST'],
        requirements: ['courseId' => self::COURSE_ID_REQUIREMENT, 'moduleId' => self::COURSE_ID_REQUIREMENT],
    )]
    public function addLesson(Request $request, string $courseId, string $moduleId): Response
    {
        if (null !== $invalid = $this->invalidCsrf($request, 'add_lesson')) {
            return $invalid;
        }

        try {
            ($this->addLesson)(new AddLessonCommand(
                $this->actorId(),
                $courseId,
                $moduleId,
                $request->request->getString('title'),
            ));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'app_course_edit', ['courseId' => $courseId]);
        }

        $this->addFlash('success', 'Lesson added.');

        return $this->redirectToRoute('app_course_edit', ['courseId' => $courseId]);
    }

    #[Route(
        '/courses/{courseId}/modules/{moduleId}/lessons/{lessonId}/delete',
        name: 'app_course_lesson_remove',
        methods: ['POST'],
        requirements: [
            'courseId' => self::COURSE_ID_REQUIREMENT,
            'moduleId' => self::COURSE_ID_REQUIREMENT,
            'lessonId' => self::COURSE_ID_REQUIREMENT,
        ],
    )]
    public function removeLesson(Request $request, string $courseId, string $moduleId, string $lessonId): Response
    {
        if (null !== $invalid = $this->invalidCsrf($request, 'remove_lesson')) {
            return $invalid;
        }

        try {
            ($this->removeLesson)(new RemoveLessonCommand($this->actorId(), $courseId, $moduleId, $lessonId));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'app_course_edit', ['courseId' => $courseId]);
        }

        $this->addFlash('success', 'Lesson removed.');

        return $this->redirectToRoute('app_course_edit', ['courseId' => $courseId]);
    }

    #[Route(
        '/courses/{courseId}/modules/{moduleId}/lessons/reorder',
        name: 'app_course_lessons_reorder',
        methods: ['POST'],
        requirements: ['courseId' => self::COURSE_ID_REQUIREMENT, 'moduleId' => self::COURSE_ID_REQUIREMENT],
    )]
    public function reorderLessons(Request $request, string $courseId, string $moduleId): Response
    {
        if (null !== $invalid = $this->invalidCsrf($request, 'reorder_lessons')) {
            return $invalid;
        }

        try {
            ($this->reorderLessons)(new ReorderLessonsCommand(
                $this->actorId(),
                $courseId,
                $moduleId,
                $this->idList($request, 'lesson_ids'),
            ));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'app_course_edit', ['courseId' => $courseId]);
        }

        $this->addFlash('success', 'Lesson order saved.');

        return $this->redirectToRoute('app_course_edit', ['courseId' => $courseId]);
    }

    #[Route(
        '/courses/{courseId}/lessons/{lessonId}/content',
        name: 'app_course_lesson_content',
        methods: ['POST'],
        requirements: ['courseId' => self::COURSE_ID_REQUIREMENT, 'lessonId' => self::COURSE_ID_REQUIREMENT],
    )]
    public function saveContent(Request $request, string $courseId, string $lessonId): Response
    {
        if (null !== $invalid = $this->invalidCsrf($request, 'save_content')) {
            return $invalid;
        }

        try {
            ($this->saveLessonContent)(new SaveLessonContentCommand(
                $this->actorId(),
                $courseId,
                $lessonId,
                $request->request->getString('content_html'),
                $this->metadata($request),
            ));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'app_course_edit', ['courseId' => $courseId]);
        }

        $this->addFlash('success', 'Lesson content saved.');

        return $this->redirectToRoute('app_course_edit', ['courseId' => $courseId]);
    }

    #[Route(
        '/courses/{courseId}/submit',
        name: 'app_course_submit',
        methods: ['POST'],
        requirements: ['courseId' => self::COURSE_ID_REQUIREMENT],
    )]
    public function submit(Request $request, string $courseId): Response
    {
        if (null !== $invalid = $this->invalidCsrf($request, 'submit_review')) {
            return $invalid;
        }

        try {
            ($this->submitForReview)(new SubmitForReviewCommand($this->actorId(), $courseId));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'app_course_edit', ['courseId' => $courseId]);
        }

        $this->addFlash('success', 'Course submitted for review.');

        return $this->redirectToRoute('app_course_edit', ['courseId' => $courseId]);
    }

    #[Route(
        '/courses/{courseId}/reject',
        name: 'app_course_reject',
        methods: ['POST'],
        requirements: ['courseId' => self::COURSE_ID_REQUIREMENT],
    )]
    public function reject(Request $request, string $courseId): Response
    {
        if (null !== $invalid = $this->invalidCsrf($request, 'reject_review')) {
            return $invalid;
        }

        try {
            ($this->rejectReview)(new RejectReviewCommand($this->actorId(), $courseId));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'app_course_edit', ['courseId' => $courseId]);
        }

        $this->addFlash('success', 'Review rejected; the version is editable again.');

        return $this->redirectToRoute('app_course_edit', ['courseId' => $courseId]);
    }

    #[Route(
        '/courses/{courseId}/publish',
        name: 'app_course_publish',
        methods: ['POST'],
        requirements: ['courseId' => self::COURSE_ID_REQUIREMENT],
    )]
    public function publish(Request $request, string $courseId): Response
    {
        if (null !== $invalid = $this->invalidCsrf($request, 'publish_course')) {
            return $invalid;
        }

        try {
            ($this->publishCourse)(new PublishCourseCommand($this->actorId(), $courseId));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'app_course_edit', ['courseId' => $courseId]);
        }

        $this->addFlash('success', 'Course published.');

        return $this->redirectToRoute('app_course_edit', ['courseId' => $courseId]);
    }

    #[Route(
        '/courses/{courseId}/archive',
        name: 'app_course_archive',
        methods: ['POST'],
        requirements: ['courseId' => self::COURSE_ID_REQUIREMENT],
    )]
    public function archive(Request $request, string $courseId): Response
    {
        if (null !== $invalid = $this->invalidCsrf($request, 'archive_course')) {
            return $invalid;
        }

        try {
            ($this->archiveCourse)(new ArchiveCourseCommand($this->actorId(), $courseId));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'app_course_edit', ['courseId' => $courseId]);
        }

        $this->addFlash('success', 'Course archived.');

        return $this->redirectToRoute('app_course_edit', ['courseId' => $courseId]);
    }

    #[Route(
        '/courses/{courseId}/draft',
        name: 'app_course_new_draft',
        methods: ['POST'],
        requirements: ['courseId' => self::COURSE_ID_REQUIREMENT],
    )]
    public function newDraft(Request $request, string $courseId): Response
    {
        if (null !== $invalid = $this->invalidCsrf($request, 'new_draft')) {
            return $invalid;
        }

        try {
            ($this->createDraftVersion)(new CreateDraftVersionCommand($this->actorId(), $courseId));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'app_course_edit', ['courseId' => $courseId]);
        }

        $this->addFlash('success', 'New draft version created.');

        return $this->redirectToRoute('app_course_edit', ['courseId' => $courseId]);
    }

    private function actorId(): string
    {
        $user = $this->getUser();

        if (!$user instanceof DomainUserAwareInterface) {
            throw new AccessDeniedHttpException('An authenticated user is required.');
        }

        return $user->domainUserId();
    }

    /**
     * @return list<array{
     *     id: string,
     *     organisationId: string,
     *     organisationName: string,
     *     slug: string,
     *     title: string,
     *     status: string,
     *     versionNumber: null|int,
     *     currentPublishedVersionId: null|string,
     *     visibility: string
     * }>
     */
    private function listVisibleCourses(string $actorId): array
    {
        $byId = [];
        $organisationNames = [];

        foreach ($this->organisations->forUser($actorId) as $organisation) {
            $organisationNames[$organisation['id']] = $organisation['name'];

            try {
                $courses = ($this->listCourses)(new ListCoursesQuery($actorId, $organisation['id']));
            } catch (ForbiddenException) {
                continue;
            }

            foreach ($courses as $course) {
                if (isset($byId[$course['id']])) {
                    continue;
                }

                try {
                    $detail = ($this->getCourse)(new GetCourseQuery($actorId, $course['id']));
                } catch (ForbiddenException|NotFoundException) {
                    continue;
                }

                $version = $detail['version'];

                $byId[$course['id']] = [
                    'id' => $detail['id'],
                    'organisationId' => $detail['organisationId'],
                    'organisationName' => $organisationNames[$detail['organisationId']] ?? '',
                    'slug' => $detail['slug'],
                    'title' => $version['title'] ?? '',
                    'status' => $version['status'] ?? 'none',
                    'versionNumber' => $version['versionNumber'] ?? null,
                    'currentPublishedVersionId' => $detail['currentPublishedVersionId'],
                    'visibility' => $detail['visibility'],
                ];
            }
        }

        $result = array_values($byId);
        usort(
            $result,
            static fn (array $left, array $right): int => strcmp((string) $left['title'], (string) $right['title']),
        );

        return $result;
    }

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
    private function courseForEditing(string $actorId, string $courseId): array
    {
        try {
            return ($this->getCourse)(new GetCourseQuery($actorId, $courseId));
        } catch (ForbiddenException) {
            throw new AccessDeniedHttpException('You are not allowed to view this course.');
        } catch (NotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function moved(array $ids, int $index, int $offset): array
    {
        $target = $index + $offset;

        if (!isset($ids[$index], $ids[$target])) {
            return $ids;
        }

        $copy = $ids;
        $copy[$index] = $ids[$target];
        $copy[$target] = $ids[$index];

        return array_values($copy);
    }

    /**
     * @return list<string>
     */
    private function idList(Request $request, string $field): array
    {
        $ids = [];

        foreach ($request->request->all($field) as $value) {
            if (\is_string($value) && '' !== $value) {
                $ids[] = $value;
            }
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(Request $request): array
    {
        $raw = trim($request->request->getString('metadata'));

        if ('' === $raw) {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withErrors(['metadata' => ['Metadata must be valid JSON.']]);
        }

        if (!\is_array($decoded)) {
            throw ValidationException::withErrors(['metadata' => ['Metadata must be a JSON object.']]);
        }

        $metadata = [];

        foreach ($decoded as $key => $value) {
            if (\is_string($key)) {
                $metadata[$key] = $value;
            }
        }

        return $metadata;
    }

    private function invalidCsrf(Request $request, string $tokenId): ?Response
    {
        if ($this->isCsrfTokenValid($tokenId, $request->request->getString('_csrf_token'))) {
            return null;
        }

        return new Response('Invalid CSRF token.', Response::HTTP_BAD_REQUEST);
    }

    /**
     * @param array<string, string> $params
     */
    private function failure(\Throwable $exception, string $route, array $params = []): Response
    {
        if ($exception instanceof ForbiddenException) {
            throw new AccessDeniedHttpException('You are not allowed to perform this action.');
        }

        if ($exception instanceof NotFoundException) {
            $this->addFlash('error', 'The requested course content was not found.');

            return $this->redirectToRoute($route, $params);
        }

        if ($exception instanceof ValidationException) {
            $this->addValidationFlash($exception);

            return $this->redirectToRoute($route, $params);
        }

        if ($exception instanceof ConflictException || $exception instanceof DomainException) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute($route, $params);
        }

        throw $exception;
    }

    private function addValidationFlash(ValidationException $exception): void
    {
        $messages = [];

        foreach ($exception->errors() as $fieldMessages) {
            foreach ($fieldMessages as $message) {
                $messages[] = $message;
            }
        }

        $this->addFlash('error', [] === $messages ? $exception->getMessage() : implode(' ', $messages));
    }
}
