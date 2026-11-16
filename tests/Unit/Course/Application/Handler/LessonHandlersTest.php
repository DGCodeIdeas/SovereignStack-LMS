<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\AddLessonCommand;
use SovereignStack\Lms\Course\Application\Command\RemoveLessonCommand;
use SovereignStack\Lms\Course\Application\Command\ReorderLessonsCommand;
use SovereignStack\Lms\Course\Application\Handler\AddLessonHandler;
use SovereignStack\Lms\Course\Application\Handler\RemoveLessonHandler;
use SovereignStack\Lms\Course\Application\Handler\ReorderLessonsHandler;
use SovereignStack\Lms\Course\Domain\Entity\Lesson;
use SovereignStack\Lms\Course\Domain\Exception\CourseModuleNotFoundException;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotEditableException;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Tests\Unit\Course\Application\CourseApplicationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class LessonHandlersTest extends CourseApplicationTestCase
{
    public function testAddLessonAppendsAtNextPosition(): void
    {
        [$course, $version] = $this->draftCourse();
        $module = $this->addModule($version);
        $this->addLesson($module, 'Existing');

        $lessonId = $this->addHandler()(new AddLessonCommand(
            self::OWNER_ID,
            $course->id()->value(),
            $module->id()->value(),
            '  New lesson ',
        ));

        $lesson = $this->lessons->findById($lessonId);
        self::assertNotNull($lesson);
        self::assertSame('New lesson', $lesson->title());
        self::assertSame(1, $lesson->position());
        self::assertTrue($this->audit->hasEvent('course.lesson_added'));
    }

    public function testAddLessonRejectsModuleFromAnotherCourse(): void
    {
        [$course] = $this->draftCourse();
        [, $otherVersion] = $this->draftCourse('other', '01900000-0000-7000-8000-000000000020');
        $foreignModule = $this->addModule($otherVersion);

        $this->expectException(CourseModuleNotFoundException::class);

        $this->addHandler()(new AddLessonCommand(
            self::OWNER_ID,
            $course->id()->value(),
            $foreignModule->id()->value(),
            'Lesson',
        ));
    }

    public function testAddLessonRejectsPublishedVersion(): void
    {
        [$course] = $this->publishedCourse();

        $this->expectException(CourseNotEditableException::class);

        $this->addHandler()(new AddLessonCommand(
            self::OWNER_ID,
            $course->id()->value(),
            '01900000-0000-7000-8000-000000000099',
            'Lesson',
        ));
    }

    public function testRemoveLessonCompactsPositions(): void
    {
        [$course, $version] = $this->draftCourse();
        $module = $this->addModule($version);
        $first = $this->addLesson($module, 'First');
        $second = $this->addLesson($module, 'Second');
        $third = $this->addLesson($module, 'Third');

        $this->removeHandler()(new RemoveLessonCommand(
            self::OWNER_ID,
            $course->id()->value(),
            $module->id()->value(),
            $second->id()->value(),
        ));

        $remaining = $this->lessons->listByModule($module->id());
        self::assertSame([$first->id()->value(), $third->id()->value()], $this->ids($remaining));
        self::assertSame([0, 1], array_map(static fn (Lesson $lesson): int => $lesson->position(), $remaining));
        self::assertTrue($this->audit->hasEvent('course.lesson_removed'));
    }

    public function testReorderLessonsAppliesExactSet(): void
    {
        [$course, $version] = $this->draftCourse();
        $module = $this->addModule($version);
        $first = $this->addLesson($module, 'First');
        $second = $this->addLesson($module, 'Second');
        $third = $this->addLesson($module, 'Third');

        $ordered = $this->reorderHandler()(new ReorderLessonsCommand(
            self::OWNER_ID,
            $course->id()->value(),
            $module->id()->value(),
            [$second->id()->value(), $third->id()->value(), $first->id()->value()],
        ));

        self::assertSame([$second->id()->value(), $third->id()->value(), $first->id()->value()], $ordered);
        self::assertSame($ordered, $this->ids($this->lessons->listByModule($module->id())));
        self::assertTrue($this->audit->hasEvent('course.lessons_reordered'));
    }

    public function testReorderLessonsRejectsForeignLesson(): void
    {
        [$course, $version] = $this->draftCourse();
        $module = $this->addModule($version);
        $first = $this->addLesson($module, 'First');
        $second = $this->addLesson($module, 'Second');
        $foreign = $this->addLesson($module, 'Foreign');
        $this->lessons->delete($foreign->id());

        try {
            $this->reorderHandler()(new ReorderLessonsCommand(
                self::OWNER_ID,
                $course->id()->value(),
                $module->id()->value(),
                [$second->id()->value(), $first->id()->value(), $foreign->id()->value()],
            ));
            self::fail('An unknown lesson must be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('lessonIds', $exception->errors());
        }
    }

    private function addHandler(): AddLessonHandler
    {
        return new AddLessonHandler(
            $this->policy,
            $this->versionResolver,
            $this->courses,
            $this->modules,
            $this->lessons,
            $this->audit,
            $this->uuid,
            $this->clock,
        );
    }

    private function removeHandler(): RemoveLessonHandler
    {
        return new RemoveLessonHandler(
            $this->policy,
            $this->versionResolver,
            $this->courses,
            $this->modules,
            $this->lessons,
            $this->audit,
            $this->transactions,
            $this->clock,
        );
    }

    private function reorderHandler(): ReorderLessonsHandler
    {
        return new ReorderLessonsHandler(
            $this->policy,
            $this->versionResolver,
            $this->courses,
            $this->modules,
            $this->lessons,
            $this->audit,
            $this->transactions,
        );
    }

    /**
     * @param list<Lesson> $lessons
     *
     * @return list<string>
     */
    private function ids(array $lessons): array
    {
        return array_map(static fn (Lesson $lesson): string => $lesson->id()->value(), $lessons);
    }
}
