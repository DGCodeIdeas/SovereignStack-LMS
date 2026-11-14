<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Application\Exception;

use SovereignStack\Lms\Shared\Domain\Result\ErrorCode;

class NotFoundException extends ApplicationException
{
    public function __construct(
        string $message = 'The requested resource was not found.',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, ErrorCode::NOT_FOUND, $previous);
    }
}
