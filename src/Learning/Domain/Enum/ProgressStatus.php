<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Domain\Enum;

enum ProgressStatus: string
{
    case NOT_STARTED = 'not_started';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
}
