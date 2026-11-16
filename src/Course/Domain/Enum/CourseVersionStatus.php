<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Enum;

enum CourseVersionStatus: string
{
    case DRAFT = 'draft';
    case REVIEW = 'review';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
}
