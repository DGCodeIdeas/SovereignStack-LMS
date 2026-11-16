<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Course\Domain\Entity\Course;
use SovereignStack\Lms\Course\Domain\Enum\CourseVisibility;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;

/**
 * @internal
 *
 * @coversNothing
 */
final class CourseTest extends TestCase
{
    private const ID = '01900000-0000-7000-8000-000000003001';
    private const ORGANISATION_ID = '01900000-0000-7000-8000-000000003002';
    private const OWNER_ID = '01900000-0000-7000-8000-000000003003';
    private const VERSION_ID = '01900000-0000-7000-8000-000000003004';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-15T10:00:00+00:00');
    }

    public function testCreateBuildsUnpublishedOrganisationOnlyCourse(): void
    {
        $course = $this->create();

        self::assertSame(self::ID, $course->id()->value());
        self::assertSame(self::ORGANISATION_ID, $course->organisationId()->value());
        self::assertSame(self::OWNER_ID, $course->ownerUserId()->value());
        self::assertSame('intro-to-php', $course->slug());
        self::assertSame(CourseVisibility::ORGANISATION_ONLY, $course->visibility());
        self::assertNull($course->currentPublishedVersionId());
        self::assertNull($course->currentPublishedVersionCourseId());
        self::assertEquals($this->now, $course->createdAt());
        self::assertEquals($this->now, $course->updatedAt());
    }

    public function testRejectsSlugWithUppercaseOrSpaces(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->create('Intro To PHP');
    }

    public function testRejectsSlugLongerThan255Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->create(str_repeat('a', 256));
    }

    public function testChangeSlugUpdatesSlugAndTimestamp(): void
    {
        $course = $this->create();
        $changedAt = $this->now->modify('+1 hour');

        $course->changeSlug('advanced-php', $changedAt);

        self::assertSame('advanced-php', $course->slug());
        self::assertEquals($changedAt, $course->updatedAt());
    }

    public function testChangeVisibilityUpdatesVisibilityAndTimestamp(): void
    {
        $course = $this->create();
        $changedAt = $this->now->modify('+1 hour');

        $course->changeVisibility(CourseVisibility::PUBLIC, $changedAt);

        self::assertSame(CourseVisibility::PUBLIC, $course->visibility());
        self::assertEquals($changedAt, $course->updatedAt());
    }

    public function testPublishVersionSetsBothPublishedColumns(): void
    {
        $course = $this->create();
        $publishedAt = $this->now->modify('+2 hours');

        $course->publishVersion(self::VERSION_ID, $publishedAt);

        self::assertSame(self::VERSION_ID, $course->currentPublishedVersionId());
        self::assertSame(self::ID, $course->currentPublishedVersionCourseId());
        self::assertEquals($publishedAt, $course->updatedAt());
    }

    public function testPublishVersionRejectsEmptyVersionId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->create()->publishVersion(' ', $this->now);
    }

    public function testClearPublishedVersionResetsBothColumns(): void
    {
        $course = $this->create();
        $course->publishVersion(self::VERSION_ID, $this->now);

        $clearedAt = $this->now->modify('+3 hours');
        $course->clearPublishedVersion($clearedAt);

        self::assertNull($course->currentPublishedVersionId());
        self::assertNull($course->currentPublishedVersionCourseId());
        self::assertEquals($clearedAt, $course->updatedAt());
    }

    private function create(string $slug = 'intro-to-php'): Course
    {
        return Course::create(
            CourseId::fromString(self::ID),
            OrganisationIdReference::fromString(self::ORGANISATION_ID),
            UserIdReference::fromString(self::OWNER_ID),
            $slug,
            CourseVisibility::ORGANISATION_ONLY,
            $this->now,
        );
    }
}
