<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Shared\Clock;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Shared\Infrastructure\Clock\SystemClock;

/**
 * @internal
 *
 * @coversNothing
 */
final class SystemClockTest extends TestCase
{
    public function testNowReturnsUtcDateTimeImmutable(): void
    {
        $now = (new SystemClock())->now();

        self::assertInstanceOf(\DateTimeImmutable::class, $now);
        self::assertSame('UTC', $now->getTimezone()->getName());
    }

    public function testNowIsWithinFiveSecondsOfSystemTime(): void
    {
        $now = (new SystemClock())->now();
        $expected = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        self::assertLessThanOrEqual(5, abs($now->getTimestamp() - $expected->getTimestamp()));
    }
}
