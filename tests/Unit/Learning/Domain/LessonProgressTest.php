<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Learning\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Learning\Domain\Entity\LessonProgress;
use SovereignStack\Lms\Learning\Domain\Enum\ProgressStatus;
use SovereignStack\Lms\Learning\Domain\Exception\ProgressConflictException;
use SovereignStack\Lms\Learning\Domain\ValueObject\LessonProgressId;

/**
 * @internal
 *
 * @coversNothing
 */
final class LessonProgressTest extends TestCase
{
    private const ID = '01900000-0000-7000-8000-000000004301';
    private const ENROLLMENT_ID = '01900000-0000-7000-8000-000000004302';
    private const LESSON_ID = '01900000-0000-7000-8000-000000004303';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-16T10:00:00+00:00');
    }

    public function testCreateStartsNotStarted(): void
    {
        $progress = $this->create();

        self::assertSame(self::ID, $progress->id()->value());
        self::assertSame(self::ENROLLMENT_ID, $progress->enrollmentId());
        self::assertSame(self::LESSON_ID, $progress->lessonId());
        self::assertSame(ProgressStatus::NOT_STARTED, $progress->status());
        self::assertSame([], $progress->progressData());
        self::assertNull($progress->startedAt());
        self::assertNull($progress->completedAt());
        self::assertEquals($this->now, $progress->updatedAt());
        self::assertSame(1, $progress->version());
    }

    public function testMarkStartedAndCompleted(): void
    {
        $progress = $this->create();
        $startedAt = $this->now->modify('+1 minute');
        $completedAt = $this->now->modify('+2 minutes');

        $progress->markStarted($startedAt);
        self::assertSame(ProgressStatus::IN_PROGRESS, $progress->status());
        self::assertEquals($startedAt, $progress->startedAt());

        $progress->markCompleted($completedAt);
        self::assertSame(ProgressStatus::COMPLETED, $progress->status());
        self::assertEquals($startedAt, $progress->startedAt());
        self::assertEquals($completedAt, $progress->completedAt());
    }

    public function testReplaceProgressDataUpdatesTimestamp(): void
    {
        $progress = $this->create();
        $updatedAt = $this->now->modify('+5 minutes');

        $progress->replaceProgressData(['position' => 3, 'answers' => ['a', 'b']], $updatedAt);

        self::assertSame(['position' => 3, 'answers' => ['a', 'b']], $progress->progressData());
        self::assertEquals($updatedAt, $progress->updatedAt());
    }

    public function testTransitionToSameStatusIsIdempotent(): void
    {
        $progress = $this->create();
        $progress->markStarted($this->now->modify('+1 minute'));
        $updatedAt = $progress->updatedAt();

        $progress->markStarted($this->now->modify('+1 hour'));

        self::assertSame(ProgressStatus::IN_PROGRESS, $progress->status());
        self::assertEquals($updatedAt, $progress->updatedAt());
    }

    public function testBackwardsTransitionIsRejected(): void
    {
        $progress = $this->create();
        $progress->markCompleted($this->now);

        $this->expectException(ProgressConflictException::class);

        $progress->markStarted($this->now->modify('+1 minute'));
    }

    public function testIncrementVersion(): void
    {
        $progress = $this->create();

        $progress->incrementVersion();

        self::assertSame(2, $progress->version());
    }

    public function testRestorePreservesProgressDataAndVersion(): void
    {
        $data = ['answers' => ['x', 'y'], 'flagged' => true];
        $startedAt = $this->now->modify('-1 hour');
        $updatedAt = $this->now;

        $progress = LessonProgress::restore(
            LessonProgressId::fromString(self::ID),
            self::ENROLLMENT_ID,
            self::LESSON_ID,
            ProgressStatus::IN_PROGRESS,
            $data,
            $startedAt,
            null,
            $updatedAt,
            4,
        );

        self::assertSame(ProgressStatus::IN_PROGRESS, $progress->status());
        self::assertSame($data, $progress->progressData());
        self::assertEquals($startedAt, $progress->startedAt());
        self::assertNull($progress->completedAt());
        self::assertEquals($updatedAt, $progress->updatedAt());
        self::assertSame(4, $progress->version());
    }

    public function testRejectsInvalidVersion(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        LessonProgress::restore(
            LessonProgressId::fromString(self::ID),
            self::ENROLLMENT_ID,
            self::LESSON_ID,
            ProgressStatus::NOT_STARTED,
            [],
            null,
            null,
            $this->now,
            0,
        );
    }

    private function create(): LessonProgress
    {
        return LessonProgress::create(
            LessonProgressId::fromString(self::ID),
            self::ENROLLMENT_ID,
            self::LESSON_ID,
            $this->now,
        );
    }
}
