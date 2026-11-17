<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Presentation\Controller;

use SovereignStack\Lms\Enrollment\Application\Command\CancelEnrollmentCommand;
use SovereignStack\Lms\Enrollment\Application\Command\CompleteEnrollmentCommand;
use SovereignStack\Lms\Enrollment\Application\Command\EnrollCommand;
use SovereignStack\Lms\Enrollment\Application\Command\RecordLessonProgressCommand;
use SovereignStack\Lms\Enrollment\Application\Contract\EnrollmentCourseView;
use SovereignStack\Lms\Enrollment\Application\Contract\EnrollmentCourseViewProviderInterface;
use SovereignStack\Lms\Enrollment\Application\Handler\CancelEnrollmentHandler;
use SovereignStack\Lms\Enrollment\Application\Handler\CompleteEnrollmentHandler;
use SovereignStack\Lms\Enrollment\Application\Handler\EnrollHandler;
use SovereignStack\Lms\Enrollment\Application\Handler\GetEnrollmentHandler;
use SovereignStack\Lms\Enrollment\Application\Handler\ListEnrollmentsHandler;
use SovereignStack\Lms\Enrollment\Application\Handler\RecordLessonProgressHandler;
use SovereignStack\Lms\Enrollment\Application\Query\GetEnrollmentQuery;
use SovereignStack\Lms\Enrollment\Application\Query\ListEnrollmentsQuery;
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

final class EnrollmentController extends AbstractController
{
    private const ID_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly ListEnrollmentsHandler $listEnrollments,
        private readonly GetEnrollmentHandler $getEnrollment,
        private readonly EnrollHandler $enroll,
        private readonly RecordLessonProgressHandler $recordLessonProgress,
        private readonly CompleteEnrollmentHandler $completeEnrollment,
        private readonly CancelEnrollmentHandler $cancelEnrollment,
        private readonly EnrollmentCourseViewProviderInterface $content,
    ) {}

    #[Route('/enrollments', name: 'app_enrollments', methods: ['GET'])]
    public function index(): Response
    {
        $enrollments = ($this->listEnrollments)(new ListEnrollmentsQuery($this->actorId()));

        foreach ($enrollments as $index => $enrollment) {
            $course = $this->courseContent($enrollment['courseId'], $enrollment['courseVersionId']);
            $enrollments[$index]['courseTitle'] = null === $course ? $enrollment['courseId'] : $course->title;
        }

        return $this->render('enrollment/index.html.twig', [
            'enrollments' => $enrollments,
        ]);
    }

    #[Route(
        '/courses/{courseId}/enroll',
        name: 'app_enroll_course',
        methods: ['POST'],
        requirements: ['courseId' => self::ID_REQUIREMENT],
    )]
    public function enroll(Request $request, string $courseId): Response
    {
        if (null !== $invalid = $this->invalidCsrf($request, 'enroll_course')) {
            return $invalid;
        }

        $actorId = $this->actorId();

        try {
            $enrollmentId = ($this->enroll)(new EnrollCommand($actorId, $actorId, $courseId));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'app_learn_course', ['courseId' => $courseId]);
        }

        $this->addFlash('success', 'You are enrolled in this course.');

        return $this->redirectToRoute('app_enrollment_show', ['enrollmentId' => $enrollmentId->value()]);
    }

    #[Route(
        '/enrollments/{enrollmentId}',
        name: 'app_enrollment_show',
        methods: ['GET'],
        requirements: ['enrollmentId' => self::ID_REQUIREMENT],
    )]
    public function show(string $enrollmentId): Response
    {
        try {
            $enrollment = ($this->getEnrollment)(new GetEnrollmentQuery($this->actorId(), $enrollmentId));
        } catch (ForbiddenException $exception) {
            throw new AccessDeniedHttpException('You are not allowed to view this enrollment.', $exception);
        } catch (NotFoundException $exception) {
            throw new NotFoundHttpException('The enrollment was not found.', $exception);
        }

        $lessons = [];
        foreach ($enrollment['lessonProgress'] as $progress) {
            $lessons[$progress['lessonId']] = $progress;
        }

        $modulesProgress = [];
        foreach ($enrollment['moduleProgress'] as $progress) {
            $modulesProgress[$progress['courseModuleId']] = $progress;
        }

        $course = $this->courseContent($enrollment['courseId'], $enrollment['courseVersionId']);

        return $this->render('enrollment/show.html.twig', [
            'enrollment' => $enrollment,
            'course' => $course,
            'modules' => $this->progressRows($course, $modulesProgress, $lessons),
        ]);
    }

    #[Route(
        '/enrollments/{enrollmentId}/lessons/{lessonId}/progress',
        name: 'app_lesson_progress',
        methods: ['POST'],
        requirements: [
            'enrollmentId' => self::ID_REQUIREMENT,
            'lessonId' => self::ID_REQUIREMENT,
        ],
    )]
    public function progress(Request $request, string $enrollmentId, string $lessonId): Response
    {
        if (null !== $invalid = $this->invalidCsrf($request, 'lesson_progress')) {
            return $invalid;
        }

        try {
            ($this->recordLessonProgress)(new RecordLessonProgressCommand(
                $this->actorId(),
                $enrollmentId,
                $lessonId,
                $request->request->getString('status'),
            ));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'app_enrollment_show', ['enrollmentId' => $enrollmentId]);
        }

        $this->addFlash('success', 'Lesson progress saved.');

        return $this->redirectToRoute('app_enrollment_show', ['enrollmentId' => $enrollmentId]);
    }

    #[Route(
        '/enrollments/{enrollmentId}/complete',
        name: 'app_complete_enrollment',
        methods: ['POST'],
        requirements: ['enrollmentId' => self::ID_REQUIREMENT],
    )]
    public function complete(Request $request, string $enrollmentId): Response
    {
        if (null !== $invalid = $this->invalidCsrf($request, 'complete_enrollment')) {
            return $invalid;
        }

        try {
            ($this->completeEnrollment)(new CompleteEnrollmentCommand($this->actorId(), $enrollmentId));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'app_enrollment_show', ['enrollmentId' => $enrollmentId]);
        }

        $this->addFlash('success', 'Course completed.');

        return $this->redirectToRoute('app_enrollment_show', ['enrollmentId' => $enrollmentId]);
    }

    #[Route(
        '/enrollments/{enrollmentId}/cancel',
        name: 'app_cancel_enrollment',
        methods: ['POST'],
        requirements: ['enrollmentId' => self::ID_REQUIREMENT],
    )]
    public function cancel(Request $request, string $enrollmentId): Response
    {
        if (null !== $invalid = $this->invalidCsrf($request, 'cancel_enrollment')) {
            return $invalid;
        }

        try {
            ($this->cancelEnrollment)(new CancelEnrollmentCommand($this->actorId(), $enrollmentId));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'app_enrollment_show', ['enrollmentId' => $enrollmentId]);
        }

        $this->addFlash('success', 'Enrollment cancelled.');

        return $this->redirectToRoute('app_enrollment_show', ['enrollmentId' => $enrollmentId]);
    }

    private function actorId(): string
    {
        $user = $this->getUser();

        if (!$user instanceof DomainUserAwareInterface) {
            throw new AccessDeniedHttpException('An authenticated user is required.');
        }

        return $user->domainUserId();
    }

    private function courseContent(string $courseId, string $versionId): ?EnrollmentCourseView
    {
        return $this->content->forVersion($courseId, $versionId);
    }

    /**
     * @param array<string, array{courseModuleId: string, status: string, startedAt: null|string, completedAt: null|string, updatedAt: string}> $modulesProgress
     * @param array<string, array{lessonId: string, status: string, startedAt: null|string, completedAt: null|string, updatedAt: string}>       $lessons
     *
     * @return list<array{
     *     id: string,
     *     title: string,
     *     isRequired: bool,
     *     status: string,
     *     lessons: list<array{id: string, title: string, contentHtml: string, status: string}>
     * }>
     */
    private function progressRows(?EnrollmentCourseView $course, array $modulesProgress, array $lessons): array
    {
        if (null === $course) {
            return [];
        }

        $rows = [];

        foreach ($course->modules as $module) {
            $moduleRow = $modulesProgress[$module['id']] ?? null;
            $lessonRows = [];

            foreach ($module['lessons'] as $lesson) {
                $lessonRow = $lessons[$lesson['id']] ?? null;

                $lessonRows[] = [
                    'id' => $lesson['id'],
                    'title' => $lesson['title'],
                    'contentHtml' => $lesson['contentHtml'],
                    'status' => $lessonRow['status'] ?? 'not_started',
                ];
            }

            $rows[] = [
                'id' => $module['id'],
                'title' => $module['title'],
                'isRequired' => $module['isRequired'],
                'status' => $moduleRow['status'] ?? 'not_started',
                'lessons' => $lessonRows,
            ];
        }

        return $rows;
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
            throw new AccessDeniedHttpException('You are not allowed to perform this action.', $exception);
        }

        if ($exception instanceof NotFoundException) {
            $this->addFlash('error', 'The requested enrollment resource was not found.');

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
