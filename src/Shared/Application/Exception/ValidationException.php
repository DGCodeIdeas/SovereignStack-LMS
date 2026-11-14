<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Application\Exception;

use SovereignStack\Lms\Shared\Domain\Result\ErrorCode;

class ValidationException extends ApplicationException
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(
        private readonly array $errors = [],
        string $message = 'Validation failed.',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, ErrorCode::VALIDATION_FAILED, $previous);
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param array<string, list<string>> $errors
     */
    public static function withErrors(array $errors, string $message = 'Validation failed.'): self
    {
        return new self($errors, $message);
    }
}
