<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Entity;

use SovereignStack\Lms\Course\Domain\Enum\CourseVisibility;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdInterface;

final class Course
{
    private const SLUG_PATTERN = '/^[a-z0-9-]+$/';

    private function __construct(
        private CourseId $id,
        private OrganisationIdInterface $organisationId,
        private UserIdInterface $ownerUserId,
        private string $slug,
        private CourseVisibility $visibility,
        private ?string $currentPublishedVersionId,
        private ?string $currentPublishedVersionCourseId,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        CourseId $id,
        OrganisationIdInterface $organisationId,
        UserIdInterface $ownerUserId,
        string $slug,
        CourseVisibility $visibility,
        \DateTimeImmutable $now,
    ): self {
        self::assertValidSlug($slug);

        return new self($id, $organisationId, $ownerUserId, $slug, $visibility, null, null, $now, $now);
    }

    public static function restore(
        CourseId $id,
        OrganisationIdInterface $organisationId,
        UserIdInterface $ownerUserId,
        string $slug,
        CourseVisibility $visibility,
        ?string $currentPublishedVersionId,
        ?string $currentPublishedVersionCourseId,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        self::assertValidSlug($slug);

        return new self(
            $id,
            $organisationId,
            $ownerUserId,
            $slug,
            $visibility,
            $currentPublishedVersionId,
            $currentPublishedVersionCourseId,
            $createdAt,
            $updatedAt,
        );
    }

    public function changeSlug(string $slug, \DateTimeImmutable $now): void
    {
        self::assertValidSlug($slug);

        $this->slug = $slug;
        $this->updatedAt = $now;
    }

    public function changeVisibility(CourseVisibility $visibility, \DateTimeImmutable $now): void
    {
        $this->visibility = $visibility;
        $this->updatedAt = $now;
    }

    /**
     * The published-version columns are a pair: the referenced version must
     * belong to this course, which the database enforces through the composite
     * foreign key. Setting both here keeps the pair consistent in memory.
     */
    public function publishVersion(string $versionId, \DateTimeImmutable $now): void
    {
        if ('' === trim($versionId)) {
            throw new \InvalidArgumentException('A published course version id cannot be empty.');
        }

        $this->currentPublishedVersionId = $versionId;
        $this->currentPublishedVersionCourseId = $this->id->value();
        $this->updatedAt = $now;
    }

    public function clearPublishedVersion(\DateTimeImmutable $now): void
    {
        $this->currentPublishedVersionId = null;
        $this->currentPublishedVersionCourseId = null;
        $this->updatedAt = $now;
    }

    public function id(): CourseId
    {
        return $this->id;
    }

    public function organisationId(): OrganisationIdInterface
    {
        return $this->organisationId;
    }

    public function ownerUserId(): UserIdInterface
    {
        return $this->ownerUserId;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function visibility(): CourseVisibility
    {
        return $this->visibility;
    }

    public function currentPublishedVersionId(): ?string
    {
        return $this->currentPublishedVersionId;
    }

    public function currentPublishedVersionCourseId(): ?string
    {
        return $this->currentPublishedVersionCourseId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function assertValidSlug(string $slug): void
    {
        if (\strlen($slug) > 255 || 1 !== preg_match(self::SLUG_PATTERN, $slug)) {
            throw new \InvalidArgumentException(sprintf('Invalid course slug "%s".', $slug));
        }
    }
}
