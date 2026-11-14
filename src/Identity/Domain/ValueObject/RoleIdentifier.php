<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Domain\ValueObject;

final readonly class RoleIdentifier
{
    public const PLATFORM_ADMIN = 'platform:admin';
    public const LMS_ADMIN = 'lms:admin';
    public const LMS_INSTRUCTOR = 'lms:instructor';
    public const LMS_LEARNER = 'lms:learner';

    private const PATTERN = '/^[a-z0-9][a-z0-9:_-]{1,63}$/';

    private function __construct(
        private string $value,
    ) {}

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw new \InvalidArgumentException(sprintf('Invalid role identifier "%s".', $value));
        }

        return new self($value);
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
