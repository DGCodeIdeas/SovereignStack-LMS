<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Domain\Enum;

enum MembershipStatus: string
{
    case ACTIVE = 'active';
    case ENDED = 'ended';
}
