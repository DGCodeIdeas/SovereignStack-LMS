<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Application\Exception;

class FileNotFoundException extends NotFoundException
{
    public function __construct(
        string $message = 'The requested file was not found.',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
