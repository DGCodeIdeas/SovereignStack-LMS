<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Handler;

use SovereignStack\Lms\Enrollment\Application\Contract\LearningProgressInterface;
use SovereignStack\Lms\Enrollment\Application\Query\GetEnrollmentQuery;
use SovereignStack\Lms\Enrollment\Application\Security\EnrollmentAccessPolicy;
use SovereignStack\Lms\Enrollment\Application\Support\Identifiers;
use SovereignStack\Lms\Enrollment\Domain\Entity\Enrollment;
use SovereignStack\Lms\Enrollment\Domain\Exception\EnrollmentNotFoundException;
use SovereignStack\Lms\Enrollment\Domain\Repository\CourseCompletionRepositoryInterface;
use SovereignStack\Lms\Enrollment\Domain\Repository\EnrollmentRepositoryInterface;

final readonly class GetEnrollmentHandler
{
    public function __construct(
        private EnrollmentAccessPolicy $policy,
        private EnrollmentRepositoryInterface $enrollments,
        private LearningProgressInterface $learningProgress,
        private CourseCompletionRepositoryInterface $completions,
    ) {}

    /**
     * @return array{
     *     id: string,
     *     organisationId: string,
     *     learnerUserId: string,
     *     courseId: string,
     *     courseVersionId: string,
     *     status: string,
     *     enrolledAt: string,
     *     completedAt: null|string,
     *     cancelledAt: null|string,
     *     lessonProgress: list<array{
     *         lessonId: string,
     *         status: string,
     *         startedAt: null|string,
     *         completedAt: null|string,
     *         updatedAt: string
     *     }>,
     *     moduleProgress: list<array{
     *         courseModuleId: string,
     *         status: string,
     *         startedAt: null|string,
     *         completedAt: null|string,
     *         updatedAt: string
     *     }>,
     *     completion: null|array{id: string, completedAt: string}
     * }
     */
    public function __invoke(GetEnrollmentQuery $query): array
    {
        $enrollmentId = Identifiers::enrollmentId($query->enrollmentId);
        $enrollment = $this->enrollments->findById($enrollmentId)
            ?? throw EnrollmentNotFoundException::forId($query->enrollmentId);

        $this->policy->requireSelfOrManager($query->actorUserId, $enrollment);

        return [
            'id' => $enrollment->id()->value(),
            'organisationId' => $enrollment->organisationId()->value(),
            'learnerUserId' => $enrollment->learnerUserId()->value(),
            'courseId' => $enrollment->courseId(),
            'courseVersionId' => $enrollment->courseVersionId(),
            'status' => $enrollment->status()->value,
            'enrolledAt' => $enrollment->enrolledAt()->format(\DATE_ATOM),
            'completedAt' => $enrollment->completedAt()?->format(\DATE_ATOM),
            'cancelledAt' => $enrollment->cancelledAt()?->format(\DATE_ATOM),
            'lessonProgress' => $this->lessonProgressPayload($enrollment),
            'moduleProgress' => $this->moduleProgressPayload($enrollment),
            'completion' => $this->completionPayload($enrollment),
        ];
    }

    /**
     * @return list<array{
     *     lessonId: string,
     *     status: string,
     *     startedAt: null|string,
     *     completedAt: null|string,
     *     updatedAt: string
     * }>
     */
    private function lessonProgressPayload(Enrollment $enrollment): array
    {
        return array_map(
            static fn (array $progress): array => $progress,
            $this->learningProgress->lessonsForEnrollment($enrollment->id()->value()),
        );
    }

    /**
     * @return list<array{
     *     courseModuleId: string,
     *     status: string,
     *     startedAt: null|string,
     *     completedAt: null|string,
     *     updatedAt: string
     * }>
     */
    private function moduleProgressPayload(Enrollment $enrollment): array
    {
        return array_map(
            static fn (array $progress): array => $progress,
            $this->learningProgress->modulesForEnrollment($enrollment->id()->value()),
        );
    }

    /**
     * @return null|array{id: string, completedAt: string}
     */
    private function completionPayload(Enrollment $enrollment): ?array
    {
        $completion = $this->completions->findByEnrollment($enrollment->id()->value());

        if (null === $completion) {
            return null;
        }

        return [
            'id' => $completion->id()->value(),
            'completedAt' => $completion->completedAt()->format(\DATE_ATOM),
        ];
    }
}
