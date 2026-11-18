<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Domain\Repository;

use SovereignStack\Lms\Identity\Domain\Entity\User;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function updateLoginState(User $user): void;

    public function findById(UserId $id): ?User;

    public function findByEmail(Email $email): ?User;

    public function emailExists(Email $email): bool;

    /**
     * @return list<array{
     *     id: string,
     *     email: string,
     *     status: string,
     *     displayName: string,
     *     createdAt: string,
     *     lastLoginAt: null|string
     * }>
     */
    public function search(string $query, int $limit, int $offset): array;

    public function countSearch(string $query): int;
}
