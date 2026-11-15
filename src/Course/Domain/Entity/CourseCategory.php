<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Entity;

use SovereignStack\Lms\Course\Domain\ValueObject\CourseCategoryId;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdInterface;

final class CourseCategory
{
    private const SLUG_PATTERN = '/^[a-z0-9-]+$/';

    private function __construct(
        private CourseCategoryId $id,
        private OrganisationIdInterface $organisationId,
        private string $name,
        private string $slug,
        private \DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        CourseCategoryId $id,
        OrganisationIdInterface $organisationId,
        string $name,
        string $slug,
        \DateTimeImmutable $now,
    ): self {
        self::assertValidName($name);
        self::assertValidSlug($slug);

        return new self($id, $organisationId, $name, $slug, $now);
    }

    public static function restore(
        CourseCategoryId $id,
        OrganisationIdInterface $organisationId,
        string $name,
        string $slug,
        \DateTimeImmutable $createdAt,
    ): self {
        self::assertValidName($name);
        self::assertValidSlug($slug);

        return new self($id, $organisationId, $name, $slug, $createdAt);
    }

    public function id(): CourseCategoryId
    {
        return $this->id;
    }

    public function organisationId(): OrganisationIdInterface
    {
        return $this->organisationId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private static function assertValidName(string $name): void
    {
        if ('' === trim($name) || \strlen($name) > 255) {
            throw new \InvalidArgumentException('The category name must be between 1 and 255 characters.');
        }
    }

    private static function assertValidSlug(string $slug): void
    {
        if (\strlen($slug) > 255 || 1 !== preg_match(self::SLUG_PATTERN, $slug)) {
            throw new \InvalidArgumentException(sprintf('Invalid category slug "%s".', $slug));
        }
    }
}
