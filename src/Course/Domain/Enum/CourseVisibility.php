<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Enum;

enum CourseVisibility: string
{
    case PUBLIC = 'public';
    case ORGANISATION_ONLY = 'organisation_only';
    case RESTRICTED = 'restricted';
    case ARCHIVED = 'archived';
}
