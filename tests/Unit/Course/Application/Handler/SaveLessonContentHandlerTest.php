<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\SaveLessonContentCommand;
use SovereignStack\Lms\Course\Application\Handler\SaveLessonContentHandler;
use SovereignStack\Lms\Course\Domain\Exception\LessonNotFoundException;
use SovereignStack\Lms\Tests\Unit\Course\Application\CourseApplicationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class SaveLessonContentHandlerTest extends CourseApplicationTestCase
{
    public function testStoresSanitisedContentAndMetadata(): void
    {
        [$course, $version] = $this->draftCourse();
        $module = $this->addModule($version);
        $lesson = $this->addLesson($module);

        $this->handler()(new SaveLessonContentCommand(
            self::OWNER_ID,
            $course->id()->value(),
            $lesson->id()->value(),
            '<script>alert(1)</script><p>Body</p>',
            ['format' => 'html', 'blocks' => 1],
        ));

        $content = $this->contents->findByLesson($lesson->id());
        self::assertNotNull($content);
        self::assertSame('sanitized:<script>alert(1)</script><p>Body</p>', $content->contentHtml());
        self::assertSame(['format' => 'html', 'blocks' => 1], $content->metadata());
        self::assertSame(['<script>alert(1)</script><p>Body</p>'], $this->sanitizer->inputs);

        $record = $this->audit->records[0];
        self::assertSame('course.lesson_content_saved', $record['eventType']);
        self::assertArrayNotHasKey('content_html', $record['data']);
        self::assertSame(\strlen($content->contentHtml()), $record['data']['length']);
    }

    public function testUpdatesExistingLessonContent(): void
    {
        [$course, $version] = $this->draftCourse();
        $module = $this->addModule($version);
        $lesson = $this->addLesson($module);
        $existing = $this->addContent($lesson, '<p>Old</p>');

        $this->handler()(new SaveLessonContentCommand(
            self::OWNER_ID,
            $course->id()->value(),
            $lesson->id()->value(),
            '<p>New</p>',
        ));

        $content = $this->contents->findByLesson($lesson->id());
        self::assertNotNull($content);
        self::assertSame($existing->id(), $content->id());
        self::assertSame('sanitized:<p>New</p>', $content->contentHtml());
        self::assertCount(1, $this->contents->contents);
    }

    public function testRejectsUnknownLesson(): void
    {
        [$course] = $this->draftCourse();

        $this->expectException(LessonNotFoundException::class);

        $this->handler()(new SaveLessonContentCommand(
            self::OWNER_ID,
            $course->id()->value(),
            '01900000-0000-7000-8000-000000000099',
            '<p>Body</p>',
        ));
    }

    private function handler(): SaveLessonContentHandler
    {
        return new SaveLessonContentHandler(
            $this->policy,
            $this->versionResolver,
            $this->courses,
            $this->modules,
            $this->lessons,
            $this->contents,
            $this->sanitizer,
            $this->audit,
            $this->uuid,
            $this->clock,
        );
    }
}
