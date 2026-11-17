<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Handler;

use SovereignStack\Lms\Enrollment\Application\Query\ListEnrollmentsQuery;
use SovereignStack\Lms\Enrollment\Domain\Entity\Enrollment;
use SovereignStack\Lms\Enrollment\Domain\Repository\EnrollmentRepositoryInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;

final readonly class ListEnrollmentsHandler
{
    public function __construct(
        private EnrollmentRepositoryInterface $enrollments,
    ) {}

    /**
     * @return list<array{
     *     id: string,
     *     status: string,
     *     courseId: string,
     *     courseVersionId: string,
     *     enrolledAt: string,
     *     completedAt: null|string,
     *     cancelledAt: null|string
     * }>
     */
    public function __invoke(ListEnrollmentsQuery $query): array
    {
        return array_map(
            static fn (Enrollment $enrollment): array => [
                'id' => $enrollment->id()->value(),
                'status' => $enrollment->status()->value,
                'courseId' => $enrollment->courseId(),
                'courseVersionId' => $enrollment->courseVersionId(),
                'enrolledAt' => $enrollment->enrolledAt()->format(\DATE_ATOM),
                'completedAt' => $enrollment->completedAt()?->format(\DATE_ATOM),
                'cancelledAt' => $enrollment->cancelledAt()?->format(\DATE_ATOM),
            ],
            $this->enrollments->listForLearner(UserIdReference::fromString($query->actorUserId)),
        );
    }
}
