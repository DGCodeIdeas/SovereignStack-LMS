<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Contract;

use SovereignStack\Lms\Learning\Application\Contract\ProgressInterface as LearningProgressInterface;

/**
 * Bridges the learning progress value parser to the enrollment-owned contract
 * so enrollment handlers never import the learning module directly.
 */
final readonly class ProgressStatusAdapter implements ProgressStatusInterface
{
    public function __construct(
        private LearningProgressInterface $progress,
    ) {}

    public function parse(string $status): string
    {
        return $this->progress->parse($status);
    }
}
