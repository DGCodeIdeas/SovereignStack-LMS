<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Handler;

use SovereignStack\Lms\Identity\Application\Query\GetUserQuery;
use SovereignStack\Lms\Identity\Domain\Exception\UserNotFoundException;
use SovereignStack\Lms\Identity\Domain\Repository\RoleRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\Repository\UserProfileRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\Repository\UserRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\RoleIdentifier;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;

final readonly class GetUserHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private UserProfileRepositoryInterface $profiles,
        private RoleRepositoryInterface $roles,
    ) {}

    /**
     * @return array{
     *     id: string,
     *     email: string,
     *     status: string,
     *     displayName: string,
     *     roles: list<string>
     * }
     */
    public function __invoke(GetUserQuery $query): array
    {
        try {
            $userId = UserId::fromString($query->userId);
        } catch (\InvalidArgumentException) {
            throw UserNotFoundException::forId($query->userId);
        }

        $user = $this->users->findById($userId) ?? throw UserNotFoundException::forId($query->userId);
        $profile = $this->profiles->findByUserId($userId);

        return [
            'id' => $user->id()->value(),
            'email' => $user->email()->value(),
            'status' => $user->status()->value,
            'displayName' => $profile?->displayName() ?? '',
            'roles' => array_map(
                static fn (RoleIdentifier $role): string => $role->value(),
                $this->roles->rolesForUser($userId),
            ),
        ];
    }
}
