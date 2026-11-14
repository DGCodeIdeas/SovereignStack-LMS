<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Application\Exception;

use SovereignStack\Lms\Shared\Domain\Result\ErrorCode;

class ApplicationException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        private readonly ErrorCode $errorCode = ErrorCode::APPLICATION_ERROR,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): ErrorCode
    {
        return $this->errorCode;
    }
}
