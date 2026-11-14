<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Support\Fakes;

use SovereignStack\Lms\Shared\Application\Token\TokenGeneratorInterface;

final class FakeTokenGenerator implements TokenGeneratorInterface
{
    /**
     * @var list<string>
     */
    public array $generated = [];
    private int $counter = 0;

    public function generate(): string
    {
        ++$this->counter;
        $token = 'raw-token-'.$this->counter;
        $this->generated[] = $token;

        return $token;
    }
}
