<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Query\ListCoursesQuery;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Course\Domain\Entity\Course;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;

final readonly class ListCoursesHandler
{
    public function __construct(
        private CourseAccessPolicy $policy,
        private CourseRepositoryInterface $courses,
    ) {}

    /**
     * @return list<array{
     *     id: string,
     *     slug: string,
     *     currentPublishedVersionId: null|string,
     *     visibility: string,
     *     updatedAt: string
     * }>
     */
    public function __invoke(ListCoursesQuery $query): array
    {
        $this->policy->requireList($query->actorUserId, $query->organisationId);

        try {
            $organisationId = OrganisationIdReference::fromString($query->organisationId);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withErrors(['organisationId' => [$exception->getMessage()]]);
        }

        return array_map(
            static fn (Course $course): array => [
                'id' => $course->id()->value(),
                'slug' => $course->slug(),
                'currentPublishedVersionId' => $course->currentPublishedVersionId(),
                'visibility' => $course->visibility()->value,
                'updatedAt' => $course->updatedAt()->format(\DATE_ATOM),
            ],
            $this->courses->listByOrganisation($organisationId),
        );
    }
}
