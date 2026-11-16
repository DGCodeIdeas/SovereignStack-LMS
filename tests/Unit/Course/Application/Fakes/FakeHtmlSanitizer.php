<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Fakes;

use SovereignStack\Lms\Course\Application\Contract\HtmlSanitizerInterface;

final class FakeHtmlSanitizer implements HtmlSanitizerInterface
{
    /**
     * @var list<string>
     */
    public array $inputs = [];

    public function sanitize(string $html): string
    {
        $this->inputs[] = $html;

        return 'sanitized:'.$html;
    }
}
