<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Shared;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Shared\Infrastructure\Clock\SystemClock;

final class ClockTest extends TestCase
{
    public function testReturnsUtcDateTimeImmutable(): void
    {
        $clock = new SystemClock();
        $now = $clock->now();

        $this->assertInstanceOf(\DateTimeImmutable::class, $now);
        $this->assertSame('UTC', $now->getTimezone()->getName());
    }

    public function testReturnsCurrentTime(): void
    {
        $clock = new SystemClock();
        $before = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $now = $clock->now();
        $after = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->assertGreaterThanOrEqual($before, $now);
        $this->assertLessThanOrEqual($after->modify('+1 second'), $now);
    }
}
