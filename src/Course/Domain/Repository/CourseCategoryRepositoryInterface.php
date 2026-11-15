<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Repository;

use SovereignStack\Lms\Course\Domain\Entity\CourseCategory;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseCategoryId;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdInterface;

interface CourseCategoryRepositoryInterface
{
    public function save(CourseCategory $category): void;

    public function findById(CourseCategoryId $id): ?CourseCategory;

    public function findByOrganisationAndSlug(OrganisationIdInterface $organisationId, string $slug): ?CourseCategory;

    /**
     * @return list<CourseCategory>
     */
    public function listByOrganisation(OrganisationIdInterface $organisationId): array;
}
