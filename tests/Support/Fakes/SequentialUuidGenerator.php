<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Support\Fakes;

use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;

final class SequentialUuidGenerator implements UuidGeneratorInterface
{
    private int $counter = 0;

    public function generate(): string
    {
        ++$this->counter;

        return sprintf('01900000-0000-7000-8000-%012d', $this->counter);
    }
}
