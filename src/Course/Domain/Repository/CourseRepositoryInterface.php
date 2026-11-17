<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Repository;

use SovereignStack\Lms\Course\Domain\Entity\Course;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdInterface;

interface CourseRepositoryInterface
{
    public function save(Course $course): void;

    public function findById(CourseId $id): ?Course;

    public function findByOrganisationAndSlug(OrganisationIdInterface $organisationId, string $slug): ?Course;

    /**
     * @return list<Course>
     */
    public function listByOrganisation(OrganisationIdInterface $organisationId): array;

    /**
     * Published courses visible in the learner catalogue.
     *
     * Public courses are returned for every caller. Organisation-only courses
     * are returned only when their organisation is in the provided list.
     * Restricted and archived courses are never returned.
     *
     * @param list<string> $organisationIds
     *
     * @return list<array{
     *     id: string,
     *     slug: string,
     *     title: string,
     *     description: null|string,
     *     organisationId: string,
     *     visibility: string,
     *     moduleCount: int
     * }>
     */
    public function listPublishedForLearner(array $organisationIds): array;

    public function updatePublishedVersion(Course $course): void;

    public function clearPublishedVersion(Course $course): void;
}
