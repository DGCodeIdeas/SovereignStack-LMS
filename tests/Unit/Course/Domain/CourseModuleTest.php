<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Course\Domain\Entity\CourseModule;
use SovereignStack\Lms\Course\Domain\Exception\InvalidCourseContentException;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseModuleId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;

/**
 * @internal
 *
 * @coversNothing
 */
final class CourseModuleTest extends TestCase
{
    private const ID = '01900000-0000-7000-8000-000000003201';
    private const VERSION_ID = '01900000-0000-7000-8000-000000003202';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-15T10:00:00+00:00');
    }

    public function testCreateStoresPositionAndRequirement(): void
    {
        $module = $this->create();

        self::assertSame(self::ID, $module->id()->value());
        self::assertSame(self::VERSION_ID, $module->courseVersionId()->value());
        self::assertSame('Getting Started', $module->title());
        self::assertSame('Orientation material', $module->description());
        self::assertSame(0, $module->position());
        self::assertTrue($module->isRequired());
        self::assertEquals($this->now, $module->createdAt());
        self::assertEquals($this->now, $module->updatedAt());
    }

    public function testRejectsEmptyTitle(): void
    {
        $this->expectException(InvalidCourseContentException::class);

        $this->create('   ');
    }

    public function testRejectsTitleLongerThan255Characters(): void
    {
        $this->expectException(InvalidCourseContentException::class);

        $this->create(str_repeat('a', 256));
    }

    public function testRejectsNegativePosition(): void
    {
        $this->expectException(InvalidCourseContentException::class);

        $this->create('Getting Started', -1);
    }

    public function testRenameUpdatesTitleAndTimestamp(): void
    {
        $module = $this->create();
        $renamedAt = $this->now->modify('+1 hour');

        $module->rename('Fundamentals', $renamedAt);

        self::assertSame('Fundamentals', $module->title());
        self::assertEquals($renamedAt, $module->updatedAt());
    }

    public function testChangePositionUpdatesPositionAndRejectsNegative(): void
    {
        $module = $this->create();
        $movedAt = $this->now->modify('+1 hour');

        $module->changePosition(3, $movedAt);

        self::assertSame(3, $module->position());
        self::assertEquals($movedAt, $module->updatedAt());

        $this->expectException(InvalidCourseContentException::class);

        $module->changePosition(-2, $movedAt);
    }

    public function testChangeRequirementTogglesRequired(): void
    {
        $module = $this->create();
        $changedAt = $this->now->modify('+1 hour');

        $module->changeRequirement(false, $changedAt);

        self::assertFalse($module->isRequired());
        self::assertEquals($changedAt, $module->updatedAt());
    }

    public function testRestoreKeepsPositionAndRequirement(): void
    {
        $module = CourseModule::restore(
            CourseModuleId::fromString(self::ID),
            CourseVersionId::fromString(self::VERSION_ID),
            'Getting Started',
            null,
            4,
            false,
            $this->now,
            $this->now->modify('+1 day'),
        );

        self::assertSame(4, $module->position());
        self::assertFalse($module->isRequired());
        self::assertNull($module->description());
    }

    private function create(string $title = 'Getting Started', int $position = 0): CourseModule
    {
        return CourseModule::create(
            CourseModuleId::fromString(self::ID),
            CourseVersionId::fromString(self::VERSION_ID),
            $title,
            'Orientation material',
            $position,
            true,
            $this->now,
        );
    }
}
