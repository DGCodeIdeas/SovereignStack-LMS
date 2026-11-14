<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Infrastructure\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use SovereignStack\Lms\Shared\Infrastructure\Correlation\CorrelationIdHolder;

final readonly class CorrelationIdProcessor implements ProcessorInterface
{
    public function __construct(private CorrelationIdHolder $holder) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        $correlationId = $this->holder->get();

        if (null !== $correlationId) {
            $record->extra['correlation_id'] = $correlationId;
        }

        return $record;
    }
}
