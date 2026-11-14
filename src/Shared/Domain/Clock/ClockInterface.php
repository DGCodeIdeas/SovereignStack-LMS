<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Domain\Clock;

interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
