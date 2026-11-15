<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Domain\ValueObject;

interface OrganisationIdInterface
{
    public function __toString(): string;

    public function value(): string;
}
