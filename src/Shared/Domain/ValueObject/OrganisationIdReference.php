<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Domain\ValueObject;

/**
 * Lightweight identifier used when a module only needs an organisation id as an
 * opaque string and must not depend on another module's domain value objects.
 */
final readonly class OrganisationIdReference implements OrganisationIdInterface
{
    private function __construct(
        private string $value,
    ) {}

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        $normalized = trim($value);

        if ('' === $normalized) {
            throw new \InvalidArgumentException('The organisation id cannot be empty.');
        }

        return new self($normalized);
    }

    public function value(): string
    {
        return $this->value;
    }
}
