<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Domain\Repository;

use SovereignStack\Lms\Identity\Domain\Entity\UserToken;
use SovereignStack\Lms\Identity\Domain\Enum\TokenPurpose;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;

interface UserTokenRepositoryInterface
{
    public function replaceActiveForPurpose(UserId $userId, TokenPurpose $purpose, UserToken $token): void;

    public function findByHash(string $tokenHash): ?UserToken;

    public function markConsumed(UserToken $token): void;

    public function deleteExpired(\DateTimeImmutable $before): int;
}
