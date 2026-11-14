<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Domain\Exception;

use SovereignStack\Lms\Shared\Domain\Result\ErrorCode;

class DomainException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        private readonly ErrorCode $errorCode = ErrorCode::DOMAIN_VIOLATION,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): ErrorCode
    {
        return $this->errorCode;
    }
}
