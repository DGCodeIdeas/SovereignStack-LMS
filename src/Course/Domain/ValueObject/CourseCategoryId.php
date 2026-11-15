<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\ValueObject;

final readonly class CourseCategoryId
{
    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    private function __construct(
        private string $value,
    ) {}

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));

        if (1 !== preg_match(self::PATTERN, $normalized)) {
            throw new \InvalidArgumentException(sprintf('Invalid course category id "%s".', $value));
        }

        return new self($normalized);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
