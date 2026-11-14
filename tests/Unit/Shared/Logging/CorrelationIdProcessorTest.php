<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Shared\Logging;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Shared\Infrastructure\Correlation\CorrelationIdHolder;
use SovereignStack\Lms\Shared\Infrastructure\Logging\CorrelationIdProcessor;

/**
 * @internal
 *
 * @coversNothing
 */
final class CorrelationIdProcessorTest extends TestCase
{
    public function testProcessorAddsCorrelationIdWhenPresent(): void
    {
        $holder = new CorrelationIdHolder();
        $processor = new CorrelationIdProcessor($holder);
        $holder->set('0190f0a0-0000-7000-8000-000000000000');

        $record = $this->record();

        $processed = $processor($record);

        self::assertSame('0190f0a0-0000-7000-8000-000000000000', $processed->extra['correlation_id'] ?? null);
    }

    public function testProcessorOmitsCorrelationIdWhenAbsent(): void
    {
        $holder = new CorrelationIdHolder();
        $processor = new CorrelationIdProcessor($holder);

        $processed = $processor($this->record());

        self::assertArrayNotHasKey('correlation_id', $processed->extra);
    }

    public function testHolderCanBeCleared(): void
    {
        $holder = new CorrelationIdHolder();
        $holder->set('abc');
        $holder->clear();

        self::assertNull($holder->get());
    }

    public function testProcessorPreservesExistingExtraData(): void
    {
        $holder = new CorrelationIdHolder();
        $processor = new CorrelationIdProcessor($holder);
        $holder->set('abc');

        $processed = $processor($this->record(['existing' => 'value']));

        self::assertSame('value', $processed->extra['existing'] ?? null);
        self::assertSame('abc', $processed->extra['correlation_id'] ?? null);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function record(array $extra = []): LogRecord
    {
        return new LogRecord(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            'app',
            Level::Info,
            'message',
            [],
            $extra,
        );
    }
}
