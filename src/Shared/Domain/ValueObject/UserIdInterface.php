<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Domain\ValueObject;

interface UserIdInterface
{
    public function __toString(): string;

    public function value(): string;
}
