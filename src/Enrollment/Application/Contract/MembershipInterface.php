<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Contract;

interface MembershipInterface
{
    public function isActiveMember(string $userId, string $organisationId): bool;
}
