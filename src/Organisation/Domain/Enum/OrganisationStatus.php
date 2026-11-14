<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Domain\Enum;

enum OrganisationStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case ARCHIVED = 'archived';
}
