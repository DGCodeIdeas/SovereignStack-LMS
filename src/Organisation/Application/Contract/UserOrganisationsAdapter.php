<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Application\Contract;

use SovereignStack\Lms\Organisation\Domain\Entity\Organisation;
use SovereignStack\Lms\Organisation\Domain\Repository\OrganisationRepositoryInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;

/**
 * Projects active organisation memberships into the plain arrays other
 * presentation layers render. Invalid user identifiers yield no organisations
 * rather than leaking a value-object exception to the caller.
 */
final readonly class UserOrganisationsAdapter implements UserOrganisationsInterface
{
    public function __construct(
        private OrganisationRepositoryInterface $organisations,
    ) {}

    public function forUser(string $userId): array
    {
        try {
            $user = UserIdReference::fromString($userId);
        } catch (\InvalidArgumentException) {
            return [];
        }

        return array_map(
            static fn (Organisation $organisation): array => [
                'id' => $organisation->id()->value(),
                'name' => $organisation->name(),
                'slug' => $organisation->slug(),
            ],
            $this->organisations->listForUser($user),
        );
    }
}
