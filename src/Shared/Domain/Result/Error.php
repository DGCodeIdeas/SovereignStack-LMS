<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Domain\Result;

final readonly class Error
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public ErrorCode $code,
        public string $message,
        public array $details = [],
    ) {}
}
