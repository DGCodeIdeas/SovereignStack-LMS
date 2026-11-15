<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Domain\Repository;

use SovereignStack\Lms\Identity\Domain\Entity\UserProfile;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;

interface UserProfileRepositoryInterface
{
    public function save(UserProfile $profile): void;

    public function findByUserId(UserId $userId): ?UserProfile;
}
