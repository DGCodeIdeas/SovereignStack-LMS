<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Application\Exception;

use SovereignStack\Lms\Shared\Domain\Result\ErrorCode;

class ForbiddenException extends ApplicationException
{
    public function __construct(
        string $message = 'You are not allowed to perform this action.',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, ErrorCode::FORBIDDEN, $previous);
    }
}
