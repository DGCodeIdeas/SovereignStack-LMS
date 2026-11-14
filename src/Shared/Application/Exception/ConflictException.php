<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Application\Exception;

use SovereignStack\Lms\Shared\Domain\Result\ErrorCode;

class ConflictException extends ApplicationException
{
    public function __construct(
        string $message = 'The request conflicts with the current state of the resource.',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, ErrorCode::CONFLICT, $previous);
    }
}
