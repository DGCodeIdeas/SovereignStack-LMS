<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Application\Exception;

class FileValidationException extends ValidationException
{
    public static function forFile(string $reason): self
    {
        return new self(['file' => [$reason]]);
    }
}
