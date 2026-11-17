<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Application\Contract;

interface MembershipCheckerInterface
{
    public function isActiveMember(string $userId, string $organisationId): bool;
}
