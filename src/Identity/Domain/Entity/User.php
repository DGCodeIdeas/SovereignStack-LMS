<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Domain\Entity;

use SovereignStack\Lms\Identity\Domain\Enum\UserStatus;
use SovereignStack\Lms\Identity\Domain\Exception\InvalidAccountStateException;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;

final class User
{
    private function __construct(
        private UserId $id,
        private Email $email,
        private string $passwordHash,
        private UserStatus $status,
        private ?\DateTimeImmutable $emailVerifiedAt,
        private int $failedLoginCount,
        private ?\DateTimeImmutable $lockedUntil,
        private ?\DateTimeImmutable $lastLoginAt,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {}

    public static function register(
        UserId $id,
        Email $email,
        string $passwordHash,
        \DateTimeImmutable $now,
    ): self {
        return new self(
            $id,
            $email,
            $passwordHash,
            UserStatus::PENDING_VERIFICATION,
            null,
            0,
            null,
            null,
            $now,
            $now,
        );
    }

    public static function restore(
        UserId $id,
        Email $email,
        string $passwordHash,
        UserStatus $status,
        ?\DateTimeImmutable $emailVerifiedAt,
        int $failedLoginCount,
        ?\DateTimeImmutable $lockedUntil,
        ?\DateTimeImmutable $lastLoginAt,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            $id,
            $email,
            $passwordHash,
            $status,
            $emailVerifiedAt,
            $failedLoginCount,
            $lockedUntil,
            $lastLoginAt,
            $createdAt,
            $updatedAt,
        );
    }

    public function verifyEmail(\DateTimeImmutable $now): void
    {
        if (UserStatus::ACTIVE === $this->status) {
            return;
        }

        if (UserStatus::PENDING_VERIFICATION !== $this->status) {
            throw InvalidAccountStateException::forTransition($this->status->value, UserStatus::ACTIVE->value);
        }

        $this->status = UserStatus::ACTIVE;
        $this->emailVerifiedAt = $now;
        $this->updatedAt = $now;
    }

    public function markEmailVerified(\DateTimeImmutable $now): void
    {
        if (null !== $this->emailVerifiedAt) {
            return;
        }

        $this->emailVerifiedAt = $now;
        $this->updatedAt = $now;
    }

    public function recordSuccessfulLogin(\DateTimeImmutable $now): void
    {
        $this->failedLoginCount = 0;
        $this->lockedUntil = null;
        $this->lastLoginAt = $now;
    }

    public function recordFailedLogin(\DateTimeImmutable $now, int $maxAttempts = 5, int $lockMinutes = 15): void
    {
        ++$this->failedLoginCount;

        if ($this->failedLoginCount >= $maxAttempts) {
            $this->lockedUntil = $now->modify(sprintf('+%d minutes', $lockMinutes));
        }
    }

    public function isLocked(\DateTimeImmutable $now): bool
    {
        return null !== $this->lockedUntil && $this->lockedUntil > $now;
    }

    public function canAuthenticate(\DateTimeImmutable $now): bool
    {
        return UserStatus::ACTIVE === $this->status && !$this->isLocked($now);
    }

    public function suspend(\DateTimeImmutable $now): void
    {
        $this->status = UserStatus::SUSPENDED;
        $this->updatedAt = $now;
    }

    public function deactivate(\DateTimeImmutable $now): void
    {
        $this->status = UserStatus::DEACTIVATED;
        $this->updatedAt = $now;
    }

    public function changePassword(string $passwordHash, \DateTimeImmutable $now): void
    {
        $this->passwordHash = $passwordHash;
        $this->updatedAt = $now;
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function status(): UserStatus
    {
        return $this->status;
    }

    public function emailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function failedLoginCount(): int
    {
        return $this->failedLoginCount;
    }

    public function lockedUntil(): ?\DateTimeImmutable
    {
        return $this->lockedUntil;
    }

    public function lastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
