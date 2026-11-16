<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Contract;

interface HtmlSanitizerInterface
{
    public function sanitize(string $html): string;
}
