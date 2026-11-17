<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Enrollment\Application\Handler;

use SovereignStack\Lms\Course\Domain\Exception\LessonNotFoundException;
use SovereignStack\Lms\Enrollment\Application\Command\RecordLessonProgressCommand;
use SovereignStack\Lms\Enrollment\Application\Contract\LessonProgressRecorderAdapter;
use SovereignStack\Lms\Enrollment\Application\Contract\LessonProgressRecorderInterface;
use SovereignStack\Lms\Enrollment\Application\Contract\ProgressStatusAdapter;
use SovereignStack\Lms\Enrollment\Application\Handler\RecordLessonProgressHandler;
use SovereignStack\Lms\Enrollment\Domain\Exception\InvalidEnrollmentTransitionException;
use SovereignStack\Lms\Learning\Application\LessonProgressRecorder;
use SovereignStack\Lms\Learning\Application\Progress;
use SovereignStack\Lms\Learning\Domain\Enum\ProgressStatus;
use SovereignStack\Lms\Learning\Domain\Exception\ProgressConflictException;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Tests\Support\Fakes\FakePermissionChecker;
use SovereignStack\Lms\Tests\Unit\Enrollment\Application\ApplicationTestCase;
use SovereignStack\Lms\Tests\Unit\Enrollment\Application\Fakes\FlakyLessonProgressRecorder;

/**
 * @internal
 *
 * @coversNothing
 */
final class RecordLessonProgressHandlerTest extends ApplicationTestCase
{
    public function testLearnerCanStartAndCompleteLesson(): void
    {
        $this->publishedCourse();
        $enrollment = $this->activeEnrollment();
        $this->lessonProgress->lessonModules[self::LESSON_ID] = self::MODULE_ID;
        $handler = $this->recordProgressHandler();

        $handler(new RecordLessonProgressCommand(self::LEARNER_ID, $enrollment->id()->value(), self::LESSON_ID, 'in_progress'));

        $progress = $this->lessonProgress->findByEnrollmentAndLesson($enrollment->id()->value(), self::LESSON_ID);
        self::assertNotNull($progress);
        self::assertSame(ProgressStatus::IN_PROGRESS, $progress->status());

        $module = $this->moduleProgress->findByEnrollmentAndModule($enrollment->id()->value(), self::MODULE_ID);
        self::assertNotNull($module);
        self::assertSame(ProgressStatus::IN_PROGRESS, $module->status());
        self::assertSame(1, $this->auditCount('learning.lesson_progressed'));

        $handler(new RecordLessonProgressCommand(self::LEARNER_ID, $enrollment->id()->value(), self::LESSON_ID, 'completed'));

        $progress = $this->lessonProgress->findByEnrollmentAndLesson($enrollment->id()->value(), self::LESSON_ID);
        self::assertNotNull($progress);
        self::assertSame(ProgressStatus::COMPLETED, $progress->status());

        $module = $this->moduleProgress->findByEnrollmentAndModule($enrollment->id()->value(), self::MODULE_ID);
        self::assertNotNull($module);
        self::assertSame(ProgressStatus::COMPLETED, $module->status());
        self::assertSame(2, $this->auditCount('learning.lesson_progressed'));
    }

    public function testRepeatedSameStatusIsANoOp(): void
    {
        $this->publishedCourse();
        $enrollment = $this->activeEnrollment();
        $handler = $this->recordProgressHandler();

        $handler(new RecordLessonProgressCommand(self::LEARNER_ID, $enrollment->id()->value(), self::LESSON_ID, 'in_progress'));
        $savesAfterFirst = $this->lessonProgress->saveCount;

        $handler(new RecordLessonProgressCommand(self::LEARNER_ID, $enrollment->id()->value(), self::LESSON_ID, 'in_progress'));

        self::assertSame($savesAfterFirst, $this->lessonProgress->saveCount);
        self::assertSame(1, $this->auditCount('learning.lesson_progressed'));
    }

    public function testBackwardsTransitionIsRejected(): void
    {
        $this->publishedCourse();
        $enrollment = $this->activeEnrollment();
        $handler = $this->recordProgressHandler();

        $handler(new RecordLessonProgressCommand(self::LEARNER_ID, $enrollment->id()->value(), self::LESSON_ID, 'completed'));

        $this->expectException(ProgressConflictException::class);

        $handler(new RecordLessonProgressCommand(self::LEARNER_ID, $enrollment->id()->value(), self::LESSON_ID, 'in_progress'));
    }

    public function testUnknownLessonIsRejected(): void
    {
        $this->publishedCourse();
        $enrollment = $this->activeEnrollment();

        $this->expectException(LessonNotFoundException::class);

        ($this->recordProgressHandler())(new RecordLessonProgressCommand(
            self::LEARNER_ID,
            $enrollment->id()->value(),
            '01900000-0000-7000-8000-000000000099',
            'in_progress',
        ));
    }

    public function testLessonFromAnotherVersionIsRejected(): void
    {
        $enrollment = $this->activeEnrollment();
        $otherVersion = '01900000-0000-7000-8000-000000000012';
        $this->publishCourse([], versionId: $otherVersion);

        $this->expectException(LessonNotFoundException::class);

        ($this->recordProgressHandler())(new RecordLessonProgressCommand(
            self::LEARNER_ID,
            $enrollment->id()->value(),
            self::LESSON_ID,
            'in_progress',
        ));
    }

    public function testNonActiveEnrollmentIsRejected(): void
    {
        $this->publishedCourse();
        $enrollment = $this->activeEnrollment();
        $enrollment->cancel($this->clock->now());
        $this->enrollments->save($enrollment);

        $this->expectException(InvalidEnrollmentTransitionException::class);

        ($this->recordProgressHandler())(new RecordLessonProgressCommand(
            self::LEARNER_ID,
            $enrollment->id()->value(),
            self::LESSON_ID,
            'in_progress',
        ));
    }

    public function testTransientProgressConflictIsRetried(): void
    {
        $this->publishedCourse();
        $enrollment = $this->activeEnrollment();
        $recorder = new FlakyLessonProgressRecorder($this->realRecorder(), 2);
        $handler = $this->handlerWith($recorder);

        $handler(new RecordLessonProgressCommand(self::LEARNER_ID, $enrollment->id()->value(), self::LESSON_ID, 'in_progress'));

        self::assertSame(3, $recorder->attempts);
        self::assertSame(3, $this->transactions->calls);
        self::assertSame(1, $this->lessonProgress->saveCount);
        self::assertSame(
            ProgressStatus::IN_PROGRESS,
            $this->lessonProgress->findByEnrollmentAndLesson($enrollment->id()->value(), self::LESSON_ID)?->status(),
        );
    }

    public function testPersistentProgressConflictIsRethrownAfterMaxAttempts(): void
    {
        $this->publishedCourse();
        $enrollment = $this->activeEnrollment();
        $recorder = new FlakyLessonProgressRecorder($this->realRecorder(), \PHP_INT_MAX);
        $handler = $this->handlerWith($recorder);

        try {
            $handler(new RecordLessonProgressCommand(self::LEARNER_ID, $enrollment->id()->value(), self::LESSON_ID, 'in_progress'));
            self::fail('A persistent conflict must be rethrown.');
        } catch (ProgressConflictException) {
            self::assertSame(3, $recorder->attempts);
            self::assertSame(3, $this->transactions->calls);
        }
    }

    public function testUnrelatedActorIsDenied(): void
    {
        $this->publishedCourse();
        $enrollment = $this->activeEnrollment();
        $this->setPermissions(new FakePermissionChecker(['enrollment.view.any']));

        $this->expectException(ForbiddenException::class);

        ($this->recordProgressHandler())(new RecordLessonProgressCommand(
            self::OTHER_ID,
            $enrollment->id()->value(),
            self::LESSON_ID,
            'in_progress',
        ));
    }

    private function realRecorder(): LessonProgressRecorderInterface
    {
        return new LessonProgressRecorderAdapter(
            new LessonProgressRecorder(
                $this->lessonProgress,
                $this->moduleProgress,
                $this->audit,
                $this->uuid,
            ),
        );
    }

    private function handlerWith(LessonProgressRecorderInterface $recorder): RecordLessonProgressHandler
    {
        return new RecordLessonProgressHandler(
            $this->policy,
            $this->content,
            $recorder,
            $this->enrollments,
            $this->transactions,
            $this->clock,
            new ProgressStatusAdapter(new Progress()),
        );
    }
}
