<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Application;

use SovereignStack\Lms\Learning\Application\Contract\ProgressInterface;
use SovereignStack\Lms\Learning\Domain\Enum\ProgressStatus;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;

final readonly class Progress implements ProgressInterface
{
    public static function fromString(string $status): ProgressStatus
    {
        try {
            return ProgressStatus::from($status);
        } catch (\ValueError) {
            throw ValidationException::withErrors([
                'status' => [sprintf('Unknown progress status "%s".', $status)],
            ]);
        }
    }

    public function completed(): string
    {
        return ProgressStatus::COMPLETED->value;
    }

    public function inProgress(): string
    {
        return ProgressStatus::IN_PROGRESS->value;
    }

    /**
     * @return list<string>
     */
    public function active(): array
    {
        return [$this->inProgress(), $this->completed()];
    }

    public function isCompleted(string $status): bool
    {
        return $this->completed() === $status;
    }

    public function parse(string $status): string
    {
        return self::fromString($status)->value;
    }
}
