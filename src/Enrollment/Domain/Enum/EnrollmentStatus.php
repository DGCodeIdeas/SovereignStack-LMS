<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Domain\Enum;

enum EnrollmentStatus: string
{
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
