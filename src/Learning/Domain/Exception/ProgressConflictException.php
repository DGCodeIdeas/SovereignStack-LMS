<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Domain\Exception;

use SovereignStack\Lms\Shared\Application\Exception\ConflictException;

final class ProgressConflictException extends ConflictException
{
    public static function backwardsTransition(string $from, string $to): self
    {
        return new self(sprintf('Cannot move progress from "%s" back to "%s".', $from, $to));
    }

    public static function versionMismatch(string $id, int $expected, int $actual): self
    {
        return new self(sprintf('Progress "%s" changed while it was being updated (expected version %d, found %d).', $id, $expected, $actual));
    }
}
