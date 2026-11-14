<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Infrastructure\Clock;

use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;

final class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
