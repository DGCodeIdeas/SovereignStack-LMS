<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Domain\Uuid;

interface UuidGeneratorInterface
{
    public function generate(): string;
}
