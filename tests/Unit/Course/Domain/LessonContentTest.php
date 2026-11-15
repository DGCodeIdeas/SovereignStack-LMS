<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Course\Domain\Entity\LessonContent;
use SovereignStack\Lms\Course\Domain\ValueObject\LessonId;

/**
 * @internal
 *
 * @coversNothing
 */
final class LessonContentTest extends TestCase
{
    private const ID = '01900000-0000-7000-8000-000000003401';
    private const LESSON_ID = '01900000-0000-7000-8000-000000003402';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-15T10:00:00+00:00');
    }

    public function testCreateStoresHtmlAndMetadata(): void
    {
        $content = $this->create();

        self::assertSame(self::ID, $content->id());
        self::assertSame(self::LESSON_ID, $content->lessonId()->value());
        self::assertSame('<p>Hello</p>', $content->contentHtml());
        self::assertSame(['format' => 'html'], $content->metadata());
        self::assertEquals($this->now, $content->createdAt());
        self::assertEquals($this->now, $content->updatedAt());
    }

    public function testReplaceContentUpdatesHtmlMetadataAndTimestamp(): void
    {
        $content = $this->create();
        $replacedAt = $this->now->modify('+1 hour');

        $content->replaceContent('<p>Updated</p>', ['format' => 'html', 'version' => 2], $replacedAt);

        self::assertSame('<p>Updated</p>', $content->contentHtml());
        self::assertSame(['format' => 'html', 'version' => 2], $content->metadata());
        self::assertEquals($this->now, $content->createdAt());
        self::assertEquals($replacedAt, $content->updatedAt());
    }

    public function testRestoreKeepsTimestamps(): void
    {
        $content = LessonContent::restore(
            self::ID,
            LessonId::fromString(self::LESSON_ID),
            '<p>Stored</p>',
            [],
            $this->now,
            $this->now->modify('+1 day'),
        );

        self::assertSame('<p>Stored</p>', $content->contentHtml());
        self::assertSame([], $content->metadata());
        self::assertEquals($this->now->modify('+1 day'), $content->updatedAt());
    }

    private function create(): LessonContent
    {
        return LessonContent::create(
            self::ID,
            LessonId::fromString(self::LESSON_ID),
            '<p>Hello</p>',
            ['format' => 'html'],
            $this->now,
        );
    }
}
