<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Domain\Exception;

use SovereignStack\Lms\Enrollment\Domain\Enum\EnrollmentStatus;
use SovereignStack\Lms\Shared\Domain\Exception\DomainException;

final class InvalidEnrollmentTransitionException extends DomainException
{
    /**
     * @param list<string> $allowed
     */
    private function __construct(
        private readonly string $currentStatus,
        private readonly string $attemptedStatus,
        private readonly array $allowed,
    ) {
        parent::__construct(sprintf(
            'Cannot transition an enrollment from "%s" to "%s". Allowed transitions: %s.',
            $currentStatus,
            $attemptedStatus,
            [] === $allowed ? 'none' : implode(', ', $allowed),
        ));
    }

    /**
     * @param list<EnrollmentStatus> $allowed
     */
    public static function forTransition(EnrollmentStatus $current, EnrollmentStatus $attempted, array $allowed): self
    {
        return new self(
            $current->value,
            $attempted->value,
            array_map(static fn (EnrollmentStatus $status): string => $status->value, $allowed),
        );
    }

    public function currentStatus(): string
    {
        return $this->currentStatus;
    }

    public function attemptedStatus(): string
    {
        return $this->attemptedStatus;
    }

    /**
     * @return list<string>
     */
    public function allowedStatuses(): array
    {
        return $this->allowed;
    }
}
