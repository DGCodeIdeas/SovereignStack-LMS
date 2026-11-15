<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Course\Domain\Entity\CourseCategory;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseCategoryId;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;

/**
 * @internal
 *
 * @coversNothing
 */
final class CourseCategoryTest extends TestCase
{
    private const ID = '01900000-0000-7000-8000-000000003701';
    private const ORGANISATION_ID = '01900000-0000-7000-8000-000000003702';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-15T10:00:00+00:00');
    }

    public function testCreateStoresCategory(): void
    {
        $category = $this->create();

        self::assertSame(self::ID, $category->id()->value());
        self::assertSame(self::ORGANISATION_ID, $category->organisationId()->value());
        self::assertSame('Programming', $category->name());
        self::assertSame('programming', $category->slug());
        self::assertEquals($this->now, $category->createdAt());
    }

    public function testRejectsInvalidSlug(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->create('Programming', 'Programming Languages');
    }

    public function testRejectsEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->create('   ');
    }

    private function create(string $name = 'Programming', string $slug = 'programming'): CourseCategory
    {
        return CourseCategory::create(
            CourseCategoryId::fromString(self::ID),
            OrganisationIdReference::fromString(self::ORGANISATION_ID),
            $name,
            $slug,
            $this->now,
        );
    }
}
