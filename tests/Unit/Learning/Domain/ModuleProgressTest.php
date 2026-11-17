<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Learning\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Learning\Domain\Entity\ModuleProgress;
use SovereignStack\Lms\Learning\Domain\Enum\ProgressStatus;
use SovereignStack\Lms\Learning\Domain\Exception\ProgressConflictException;
use SovereignStack\Lms\Learning\Domain\ValueObject\ModuleProgressId;

/**
 * @internal
 *
 * @coversNothing
 */
final class ModuleProgressTest extends TestCase
{
    private const ID = '01900000-0000-7000-8000-000000004201';
    private const ENROLLMENT_ID = '01900000-0000-7000-8000-000000004202';
    private const MODULE_ID = '01900000-0000-7000-8000-000000004203';

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
        self::assertSame(self::MODULE_ID, $progress->courseModuleId());
        self::assertSame(ProgressStatus::NOT_STARTED, $progress->status());
        self::assertNull($progress->startedAt());
        self::assertNull($progress->completedAt());
        self::assertEquals($this->now, $progress->updatedAt());
        self::assertSame(1, $progress->version());
    }

    public function testMarkStartedMovesToInProgress(): void
    {
        $progress = $this->create();
        $startedAt = $this->now->modify('+1 minute');

        $progress->markStarted($startedAt);

        self::assertSame(ProgressStatus::IN_PROGRESS, $progress->status());
        self::assertEquals($startedAt, $progress->startedAt());
        self::assertNull($progress->completedAt());
        self::assertEquals($startedAt, $progress->updatedAt());
    }

    public function testMarkCompletedMovesToCompleted(): void
    {
        $progress = $this->create();
        $startedAt = $this->now->modify('+1 minute');
        $completedAt = $this->now->modify('+2 minutes');

        $progress->markStarted($startedAt);
        $progress->markCompleted($completedAt);

        self::assertSame(ProgressStatus::COMPLETED, $progress->status());
        self::assertEquals($startedAt, $progress->startedAt());
        self::assertEquals($completedAt, $progress->completedAt());
        self::assertEquals($completedAt, $progress->updatedAt());
    }

    public function testTransitionToSameStatusIsIdempotent(): void
    {
        $progress = $this->create();
        $progress->markStarted($this->now->modify('+1 minute'));
        $updatedAt = $progress->updatedAt();
        $startedAt = $progress->startedAt();

        $progress->markStarted($this->now->modify('+1 hour'));

        self::assertSame(ProgressStatus::IN_PROGRESS, $progress->status());
        self::assertEquals($startedAt, $progress->startedAt());
        self::assertEquals($updatedAt, $progress->updatedAt());
    }

    public function testBackwardsTransitionIsRejected(): void
    {
        $progress = $this->create();
        $progress->markStarted($this->now->modify('+1 minute'));
        $progress->markCompleted($this->now->modify('+2 minutes'));

        $this->expectException(ProgressConflictException::class);

        $progress->transitionTo(ProgressStatus::IN_PROGRESS, $this->now->modify('+3 minutes'));
    }

    public function testBackwardsToNotStartedIsRejected(): void
    {
        $progress = $this->create();
        $progress->markStarted($this->now);

        $this->expectException(ProgressConflictException::class);

        $progress->transitionTo(ProgressStatus::NOT_STARTED, $this->now->modify('+1 minute'));
    }

    public function testForwardTransitionCanSkipStates(): void
    {
        $progress = $this->create();
        $completedAt = $this->now->modify('+1 minute');

        $progress->transitionTo(ProgressStatus::COMPLETED, $completedAt);

        self::assertSame(ProgressStatus::COMPLETED, $progress->status());
        self::assertEquals($completedAt, $progress->startedAt());
        self::assertEquals($completedAt, $progress->completedAt());
    }

    public function testIncrementVersion(): void
    {
        $progress = $this->create();

        $progress->incrementVersion();

        self::assertSame(2, $progress->version());
    }

    public function testRestorePreservesStateAndVersion(): void
    {
        $startedAt = $this->now->modify('-1 hour');
        $completedAt = $this->now->modify('-30 minutes');
        $updatedAt = $this->now;

        $progress = ModuleProgress::restore(
            ModuleProgressId::fromString(self::ID),
            self::ENROLLMENT_ID,
            self::MODULE_ID,
            ProgressStatus::COMPLETED,
            $startedAt,
            $completedAt,
            $updatedAt,
            7,
        );

        self::assertSame(ProgressStatus::COMPLETED, $progress->status());
        self::assertEquals($startedAt, $progress->startedAt());
        self::assertEquals($completedAt, $progress->completedAt());
        self::assertEquals($updatedAt, $progress->updatedAt());
        self::assertSame(7, $progress->version());
    }

    public function testRejectsInvalidVersion(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ModuleProgress::restore(
            ModuleProgressId::fromString(self::ID),
            self::ENROLLMENT_ID,
            self::MODULE_ID,
            ProgressStatus::NOT_STARTED,
            null,
            null,
            $this->now,
            0,
        );
    }

    private function create(): ModuleProgress
    {
        return ModuleProgress::create(
            ModuleProgressId::fromString(self::ID),
            self::ENROLLMENT_ID,
            self::MODULE_ID,
            $this->now,
        );
    }
}
