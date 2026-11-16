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

    public function updatePublishedVersion(Course $course): void;

    public function clearPublishedVersion(Course $course): void;
}
