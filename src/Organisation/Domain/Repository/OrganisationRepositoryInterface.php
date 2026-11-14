<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Domain\Repository;

use SovereignStack\Lms\Organisation\Domain\Entity\Organisation;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdInterface;

interface OrganisationRepositoryInterface
{
    public function save(Organisation $organisation): void;

    public function findById(OrganisationId $id): ?Organisation;

    public function findBySlug(string $slug): ?Organisation;

    /**
     * @return list<Organisation>
     */
    public function listForUser(UserIdInterface $userId): array;
}
