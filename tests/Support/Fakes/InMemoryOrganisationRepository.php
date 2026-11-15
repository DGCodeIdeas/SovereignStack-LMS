<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Support\Fakes;

use SovereignStack\Lms\Organisation\Domain\Entity\Organisation;
use SovereignStack\Lms\Organisation\Domain\Repository\OrganisationRepositoryInterface;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdInterface;

final class InMemoryOrganisationRepository implements OrganisationRepositoryInterface
{
    /**
     * @var array<string, Organisation>
     */
    private array $organisations = [];

    public function save(Organisation $organisation): void
    {
        $this->organisations[$organisation->id()->value()] = $organisation;
    }

    public function findById(OrganisationId $id): ?Organisation
    {
        return $this->organisations[$id->value()] ?? null;
    }

    public function findBySlug(string $slug): ?Organisation
    {
        foreach ($this->organisations as $organisation) {
            if ($organisation->slug() === $slug) {
                return $organisation;
            }
        }

        return null;
    }

    public function listForUser(UserIdInterface $userId): array
    {
        return array_values($this->organisations);
    }

    public function count(): int
    {
        return \count($this->organisations);
    }
}
