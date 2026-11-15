<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Domain\Entity;

use SovereignStack\Lms\Organisation\Domain\Enum\OrganisationStatus;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;

final class Organisation
{
    private const SLUG_PATTERN = '/^[a-z0-9-]+$/';

    /**
     * @param array<string, mixed> $settings
     */
    private function __construct(
        private OrganisationId $id,
        private string $name,
        private string $slug,
        private OrganisationStatus $status,
        private string $timezone,
        private array $settings,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        OrganisationId $id,
        string $name,
        string $slug,
        \DateTimeImmutable $now,
    ): self {
        self::assertValidName($name);
        self::assertValidSlug($slug);

        return new self($id, $name, $slug, OrganisationStatus::ACTIVE, 'UTC', [], $now, $now);
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function restore(
        OrganisationId $id,
        string $name,
        string $slug,
        OrganisationStatus $status,
        string $timezone,
        array $settings,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self($id, $name, $slug, $status, $timezone, $settings, $createdAt, $updatedAt);
    }

    public function rename(string $name, \DateTimeImmutable $now): void
    {
        self::assertValidName($name);

        $this->name = $name;
        $this->updatedAt = $now;
    }

    public function suspend(\DateTimeImmutable $now): void
    {
        $this->status = OrganisationStatus::SUSPENDED;
        $this->updatedAt = $now;
    }

    public function id(): OrganisationId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function status(): OrganisationStatus
    {
        return $this->status;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->settings;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function assertValidName(string $name): void
    {
        if ('' === trim($name) || \strlen($name) > 255) {
            throw new \InvalidArgumentException('The organisation name must be between 1 and 255 characters.');
        }
    }

    private static function assertValidSlug(string $slug): void
    {
        if (\strlen($slug) > 255 || 1 !== preg_match(self::SLUG_PATTERN, $slug)) {
            throw new \InvalidArgumentException(sprintf('Invalid organisation slug "%s".', $slug));
        }
    }
}
