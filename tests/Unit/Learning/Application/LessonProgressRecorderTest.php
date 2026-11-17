<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Learning\Application;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Learning\Application\LessonProgressRecorder;
use SovereignStack\Lms\Learning\Domain\Enum\ProgressStatus;
use SovereignStack\Lms\Tests\Support\Fakes\RecordingAuditLogger;
use SovereignStack\Lms\Tests\Support\Fakes\SequentialUuidGenerator;
use SovereignStack\Lms\Tests\Unit\Enrollment\Application\Fakes\InMemoryLessonProgressRepository;
use SovereignStack\Lms\Tests\Unit\Enrollment\Application\Fakes\InMemoryModuleProgressRepository;

/**
 * @internal
 *
 * @coversNothing
 */
final class LessonProgressRecorderTest extends TestCase
{
    private const ACTOR_ID = '01900000-0000-7000-8000-000000000001';
    private const ENROLLMENT_ID = '01900000-0000-7000-8000-000000000002';
    private const ORGANISATION_ID = '01900000-0000-7000-8000-000000000003';
    private const MODULE_ID = '01900000-0000-7000-8000-000000000004';
    private const FIRST_LESSON_ID = '01900000-0000-7000-8000-000000000005';
    private const SECOND_LESSON_ID = '01900000-0000-7000-8000-000000000006';

    private InMemoryLessonProgressRepository $lessons;
    private InMemoryModuleProgressRepository $modules;
    private RecordingAuditLogger $audit;
    private LessonProgressRecorder $recorder;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->lessons = new InMemoryLessonProgressRepository();
        $this->modules = new InMemoryModuleProgressRepository();
        $this->audit = new RecordingAuditLogger();
        $this->recorder = new LessonProgressRecorder(
            $this->lessons,
            $this->modules,
            $this->audit,
            new SequentialUuidGenerator(),
        );
        $this->now = new \DateTimeImmutable('2026-11-16T10:00:00+00:00');
    }

    public function testModuleIsNotCompleteUntilEveryLessonIsComplete(): void
    {
        $this->lessonModules(self::FIRST_LESSON_ID, self::SECOND_LESSON_ID);

        $this->record(self::FIRST_LESSON_ID, 'completed', [self::FIRST_LESSON_ID, self::SECOND_LESSON_ID]);

        $module = $this->modules->findByEnrollmentAndModule(self::ENROLLMENT_ID, self::MODULE_ID);
        self::assertNotNull($module);
        self::assertSame(ProgressStatus::IN_PROGRESS, $module->status());
        self::assertNull($this->lessons->findByEnrollmentAndLesson(self::ENROLLMENT_ID, self::SECOND_LESSON_ID));

        $this->record(self::SECOND_LESSON_ID, 'completed', [self::FIRST_LESSON_ID, self::SECOND_LESSON_ID]);

        $module = $this->modules->findByEnrollmentAndModule(self::ENROLLMENT_ID, self::MODULE_ID);
        self::assertNotNull($module);
        self::assertSame(ProgressStatus::COMPLETED, $module->status());
    }

    public function testSingleCompletedLessonDoesNotCompleteAModuleWithUnstartedLessons(): void
    {
        $this->lessonModules(self::FIRST_LESSON_ID, self::SECOND_LESSON_ID);

        $this->record(self::FIRST_LESSON_ID, 'completed', [self::FIRST_LESSON_ID, self::SECOND_LESSON_ID]);

        $module = $this->modules->findByEnrollmentAndModule(self::ENROLLMENT_ID, self::MODULE_ID);
        self::assertNotNull($module);
        self::assertNotSame(ProgressStatus::COMPLETED, $module->status());
    }

    public function testStartedLessonMarksModuleInProgress(): void
    {
        $this->lessonModules(self::FIRST_LESSON_ID, self::SECOND_LESSON_ID);

        $this->record(self::FIRST_LESSON_ID, 'in_progress', [self::FIRST_LESSON_ID, self::SECOND_LESSON_ID]);

        $module = $this->modules->findByEnrollmentAndModule(self::ENROLLMENT_ID, self::MODULE_ID);
        self::assertNotNull($module);
        self::assertSame(ProgressStatus::IN_PROGRESS, $module->status());
    }

    public function testModuleWithZeroLessonsNeverGetsModuleProgress(): void
    {
        $this->lessonModules(self::FIRST_LESSON_ID);

        $this->record(self::FIRST_LESSON_ID, 'completed', []);

        self::assertNull($this->modules->findByEnrollmentAndModule(self::ENROLLMENT_ID, self::MODULE_ID));
        self::assertSame(0, $this->modules->saveCount);
        self::assertSame(
            ProgressStatus::COMPLETED,
            $this->lessons->findByEnrollmentAndLesson(self::ENROLLMENT_ID, self::FIRST_LESSON_ID)?->status(),
        );
    }

    public function testRepeatedSameStatusDoesNotRewriteProgressOrModule(): void
    {
        $this->lessonModules(self::FIRST_LESSON_ID);
        $this->record(self::FIRST_LESSON_ID, 'completed', [self::FIRST_LESSON_ID]);

        $lessonSaves = $this->lessons->saveCount;
        $moduleSaves = $this->modules->saveCount;
        $module = $this->modules->findByEnrollmentAndModule(self::ENROLLMENT_ID, self::MODULE_ID);
        self::assertNotNull($module);
        $version = $module->version();

        $this->record(self::FIRST_LESSON_ID, 'completed', [self::FIRST_LESSON_ID]);

        self::assertSame($lessonSaves, $this->lessons->saveCount);
        self::assertSame($moduleSaves, $this->modules->saveCount);
        self::assertCount(1, $this->audit->records);

        $reloaded = $this->modules->findByEnrollmentAndModule(self::ENROLLMENT_ID, self::MODULE_ID);
        self::assertNotNull($reloaded);
        self::assertSame($version, $reloaded->version());
    }

    private function lessonModules(string ...$lessonIds): void
    {
        foreach ($lessonIds as $lessonId) {
            $this->lessons->lessonModules[$lessonId] = self::MODULE_ID;
        }
    }

    /**
     * @param list<string> $moduleLessonIds
     */
    private function record(string $lessonId, string $target, array $moduleLessonIds): void
    {
        $this->recorder->recordLessonProgress(
            self::ACTOR_ID,
            self::ENROLLMENT_ID,
            $lessonId,
            $target,
            self::ORGANISATION_ID,
            self::MODULE_ID,
            $moduleLessonIds,
            $this->now,
        );
    }
}
