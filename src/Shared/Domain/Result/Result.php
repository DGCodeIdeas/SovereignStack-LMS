<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Domain\Result;

final readonly class Result
{
    private function __construct(
        private bool $successful,
        private mixed $value,
        private ?Error $error,
    ) {}

    public static function success(mixed $value = null): self
    {
        return new self(true, $value, null);
    }

    public static function failure(Error $error): self
    {
        return new self(false, null, $error);
    }

    public function isSuccess(): bool
    {
        return $this->successful;
    }

    public function isFailure(): bool
    {
        return !$this->successful;
    }

    public function value(): mixed
    {
        if (!$this->successful) {
            throw new \LogicException('A failed result has no value.');
        }

        return $this->value;
    }

    public function error(): Error
    {
        if ($this->successful || null === $this->error) {
            throw new \LogicException('A successful result has no error.');
        }

        return $this->error;
    }
}
