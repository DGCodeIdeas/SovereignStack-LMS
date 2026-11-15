<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Domain\Entity;

use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;

final class UserProfile
{
    /**
     * @param array<string, mixed> $metadata
     */
    private function __construct(
        private string $id,
        private UserId $userId,
        private string $displayName,
        private string $preferredTz,
        private array $metadata,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @param array<string, mixed> $metadata
     */
    public static function create(
        string $id,
        UserId $userId,
        string $displayName,
        string $preferredTz,
        array $metadata,
        \DateTimeImmutable $now,
    ): self {
        $normalizedDisplayName = trim($displayName);

        if ('' === $normalizedDisplayName) {
            throw new \InvalidArgumentException('The display name cannot be empty.');
        }

        return new self(
            $id,
            $userId,
            $normalizedDisplayName,
            '' === $preferredTz ? 'UTC' : $preferredTz,
            $metadata,
            $now,
            $now,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function restore(
        string $id,
        UserId $userId,
        string $displayName,
        string $preferredTz,
        array $metadata,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self($id, $userId, $displayName, $preferredTz, $metadata, $createdAt, $updatedAt);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function preferredTz(): string
    {
        return $this->preferredTz;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
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
