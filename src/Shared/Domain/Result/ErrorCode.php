<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Domain\Result;

enum ErrorCode: string
{
    case VALIDATION_FAILED = 'validation_failed';
    case NOT_FOUND = 'not_found';
    case CONFLICT = 'conflict';
    case FORBIDDEN = 'forbidden';
    case DOMAIN_VIOLATION = 'domain_violation';
    case APPLICATION_ERROR = 'application_error';
    case UNEXPECTED = 'unexpected';
}
