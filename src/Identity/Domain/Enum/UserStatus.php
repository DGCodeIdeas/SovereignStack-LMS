<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Domain\Enum;

enum UserStatus: string
{
    case PENDING_VERIFICATION = 'pending_verification';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case DEACTIVATED = 'deactivated';
}
