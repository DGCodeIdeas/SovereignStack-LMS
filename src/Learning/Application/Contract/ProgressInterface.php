<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Application\Contract;

interface ProgressInterface
{
    public function parse(string $status): string;
}
