<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Infrastructure\Correlation;

final class CorrelationIdHolder
{
    private ?string $correlationId = null;

    public function set(string $id): void
    {
        $this->correlationId = $id;
    }

    public function get(): ?string
    {
        return $this->correlationId;
    }

    public function clear(): void
    {
        $this->correlationId = null;
    }
}
