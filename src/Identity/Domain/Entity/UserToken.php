<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Domain\Entity;

use SovereignStack\Lms\Identity\Domain\Enum\TokenPurpose;
use SovereignStack\Lms\Identity\Domain\Exception\TokenExpiredException;
use SovereignStack\Lms\Identity\Domain\Exception\TokenInvalidException;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;

final class UserToken
{
    private function __construct(
        private string $id,
        private UserId $userId,
        private TokenPurpose $purpose,
        private string $tokenHash,
        private \DateTimeImmutable $expiresAt,
        private ?\DateTimeImmutable $consumedAt,
        private \DateTimeImmutable $createdAt,
    ) {}

    public static function issue(
        string $id,
        UserId $userId,
        TokenPurpose $purpose,
        string $tokenHash,
        \DateTimeImmutable $expiresAt,
        \DateTimeImmutable $now,
    ): self {
        if (64 !== \strlen($tokenHash)) {
            throw new \InvalidArgumentException('The token hash must be a 64 character hex digest.');
        }

        return new self($id, $userId, $purpose, $tokenHash, $expiresAt, null, $now);
    }

    public static function restore(
        string $id,
        UserId $userId,
        TokenPurpose $purpose,
        string $tokenHash,
        \DateTimeImmutable $expiresAt,
        ?\DateTimeImmutable $consumedAt,
        \DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $userId, $purpose, $tokenHash, $expiresAt, $consumedAt, $createdAt);
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now;
    }

    public function isConsumed(): bool
    {
        return null !== $this->consumedAt;
    }

    public function consume(\DateTimeImmutable $now): void
    {
        if ($this->isConsumed()) {
            throw TokenInvalidException::alreadyConsumed();
        }

        if ($this->isExpired($now)) {
            throw TokenExpiredException::at($this->expiresAt);
        }

        $this->consumedAt = $now;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function purpose(): TokenPurpose
    {
        return $this->purpose;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function consumedAt(): ?\DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
