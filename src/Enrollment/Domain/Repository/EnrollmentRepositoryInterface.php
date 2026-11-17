<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Domain\Repository;

use SovereignStack\Lms\Enrollment\Domain\Entity\Enrollment;
use SovereignStack\Lms\Enrollment\Domain\ValueObject\EnrollmentId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdInterface;

interface EnrollmentRepositoryInterface
{
    public function save(Enrollment $enrollment): void;

    public function findById(EnrollmentId $id): ?Enrollment;

    public function findForUpdate(EnrollmentId $id): ?Enrollment;

    public function findActiveByLearnerAndCourse(UserIdInterface $learnerUserId, string $courseId): ?Enrollment;

    /**
     * @return list<Enrollment>
     */
    public function listForLearner(UserIdInterface $learnerUserId): array;

    /**
     * @return list<Enrollment>
     */
    public function listForCourse(string $courseId): array;
}
