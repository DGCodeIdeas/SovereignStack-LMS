<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Support\Fakes;

use SovereignStack\Lms\Identity\Domain\Entity\UserProfile;
use SovereignStack\Lms\Identity\Domain\Repository\UserProfileRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;

final class InMemoryUserProfileRepository implements UserProfileRepositoryInterface
{
    /**
     * @var array<string, UserProfile>
     */
    private array $profiles = [];

    public function save(UserProfile $profile): void
    {
        $this->profiles[$profile->userId()->value()] = $profile;
    }

    public function findByUserId(UserId $userId): ?UserProfile
    {
        return $this->profiles[$userId->value()] ?? null;
    }
}
