<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Enrollment\Application\Fakes;

use SovereignStack\Lms\Enrollment\Application\Contract\MembershipInterface;

final class FakeMembershipChecker implements MembershipInterface
{
    /**
     * @param list<string> $memberships
     */
    public function __construct(
        private array $memberships = [],
    ) {}

    public function isActiveMember(string $userId, string $organisationId): bool
    {
        return \in_array($userId, $this->memberships, true);
    }
}
