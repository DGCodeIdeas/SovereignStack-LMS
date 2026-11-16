<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Course\Domain\Entity\CourseVersion;
use SovereignStack\Lms\Course\Domain\Enum\CourseVersionStatus;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotEditableException;
use SovereignStack\Lms\Course\Domain\Exception\InvalidCourseContentException;
use SovereignStack\Lms\Course\Domain\Exception\InvalidCourseTransitionException;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;

/**
 * @internal
 *
 * @coversNothing
 */
final class CourseVersionTest extends TestCase
{
    private const ID = '01900000-0000-7000-8000-000000003101';
    private const COURSE_ID = '01900000-0000-7000-8000-000000003102';
    private const AUTHOR_ID = '01900000-0000-7000-8000-000000003103';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-15T10:00:00+00:00');
    }

    public function testCreateDraftStartsInDraftStatus(): void
    {
        $version = $this->draft();

        self::assertSame(self::ID, $version->id()->value());
        self::assertSame(self::COURSE_ID, $version->courseId()->value());
        self::assertSame(1, $version->versionNumber());
        self::assertSame('Introduction', $version->title());
        self::assertSame('First version', $version->description());
        self::assertSame(CourseVersionStatus::DRAFT, $version->status());
        self::assertNull($version->publishedAt());
        self::assertSame(self::AUTHOR_ID, $version->createdBy()->value());
        self::assertEquals($this->now, $version->createdAt());
    }

    public function testSubmitForReviewMovesDraftToReview(): void
    {
        $version = $this->draft();
        $version->submitForReview();

        self::assertSame(CourseVersionStatus::REVIEW, $version->status());
    }

    public function testPublishMovesReviewToPublishedAndSetsPublishedAt(): void
    {
        $version = $this->draft();
        $version->submitForReview();
        $publishedAt = $this->now->modify('+1 day');

        $version->publish($publishedAt);

        self::assertSame(CourseVersionStatus::PUBLISHED, $version->status());
        self::assertEquals($publishedAt, $version->publishedAt());
    }

    public function testPublishFromDraftIsRejectedWithTransitionDetails(): void
    {
        $version = $this->draft();

        try {
            $version->publish($this->now);
            self::fail('Publishing a draft should be rejected.');
        } catch (InvalidCourseTransitionException $exception) {
            self::assertSame('draft', $exception->currentStatus());
            self::assertSame('published', $exception->attemptedStatus());
            self::assertSame(['review'], $exception->allowedStatuses());
        }
    }

    public function testRejectFromReviewReturnsToDraft(): void
    {
        $version = $this->draft();
        $version->submitForReview();

        $version->rejectFromReview();

        self::assertSame(CourseVersionStatus::DRAFT, $version->status());
    }

    public function testRejectFromPublishedIsRejected(): void
    {
        $version = $this->published();

        $this->expectException(InvalidCourseTransitionException::class);

        $version->rejectFromReview();
    }

    public function testArchiveMovesPublishedToArchived(): void
    {
        $version = $this->published();

        $version->archive();

        self::assertSame(CourseVersionStatus::ARCHIVED, $version->status());
    }

    public function testArchiveFromDraftIsRejected(): void
    {
        $this->expectException(InvalidCourseTransitionException::class);

        $this->draft()->archive();
    }

    public function testSubmitForReviewTwiceIsRejected(): void
    {
        $version = $this->draft();
        $version->submitForReview();

        $this->expectException(InvalidCourseTransitionException::class);

        $version->submitForReview();
    }

    public function testAssertEditableAllowsDraftOnly(): void
    {
        $this->draft()->assertEditable();

        $this->expectException(CourseNotEditableException::class);

        $this->published()->assertEditable();
    }

    public function testAssertImmutableRejectsPublishedAndArchived(): void
    {
        $this->draft()->assertImmutable();

        try {
            $this->published()->assertImmutable();
            self::fail('Published versions must be immutable.');
        } catch (CourseNotEditableException) {
            self::addToAssertionCount(1);
        }

        try {
            $this->restore(CourseVersionStatus::ARCHIVED)->assertImmutable();
            self::fail('Archived versions must be immutable.');
        } catch (CourseNotEditableException) {
            self::addToAssertionCount(1);
        }
    }

    public function testRejectsEmptyTitle(): void
    {
        $this->expectException(InvalidCourseContentException::class);

        CourseVersion::createDraft(
            CourseVersionId::fromString(self::ID),
            CourseId::fromString(self::COURSE_ID),
            1,
            '   ',
            null,
            $this->author(),
            $this->now,
        );
    }

    public function testRejectsVersionNumberBelowOne(): void
    {
        $this->expectException(InvalidCourseContentException::class);

        CourseVersion::createDraft(
            CourseVersionId::fromString(self::ID),
            CourseId::fromString(self::COURSE_ID),
            0,
            'Introduction',
            null,
            $this->author(),
            $this->now,
        );
    }

    public function testSubsequentDraftCanCarryNextVersionNumber(): void
    {
        $second = CourseVersion::createDraft(
            CourseVersionId::fromString('01900000-0000-7000-8000-000000003104'),
            CourseId::fromString(self::COURSE_ID),
            2,
            'Second draft',
            null,
            $this->author(),
            $this->now,
        );

        self::assertSame(2, $second->versionNumber());
        self::assertSame(CourseVersionStatus::DRAFT, $second->status());
    }

    private function draft(): CourseVersion
    {
        return CourseVersion::createDraft(
            CourseVersionId::fromString(self::ID),
            CourseId::fromString(self::COURSE_ID),
            1,
            'Introduction',
            'First version',
            $this->author(),
            $this->now,
        );
    }

    private function published(): CourseVersion
    {
        $version = $this->draft();
        $version->submitForReview();
        $version->publish($this->now->modify('+1 hour'));

        return $version;
    }

    private function restore(CourseVersionStatus $status): CourseVersion
    {
        return CourseVersion::restore(
            CourseVersionId::fromString(self::ID),
            CourseId::fromString(self::COURSE_ID),
            1,
            'Introduction',
            'First version',
            $status,
            CourseVersionStatus::PUBLISHED === $status ? $this->now : null,
            $this->author(),
            $this->now,
        );
    }

    private function author(): UserIdReference
    {
        return UserIdReference::fromString(self::AUTHOR_ID);
    }
}
