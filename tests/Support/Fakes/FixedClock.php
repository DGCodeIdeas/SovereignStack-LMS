<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Support\Fakes;

use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;

final class FixedClock implements ClockInterface
{
    public function __construct(
        private \DateTimeImmutable $now,
    ) {}

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
