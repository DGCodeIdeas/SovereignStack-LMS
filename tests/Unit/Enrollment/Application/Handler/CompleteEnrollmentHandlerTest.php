<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Enrollment\Application\Handler;

use SovereignStack\Lms\Enrollment\Application\Command\CompleteEnrollmentCommand;
use SovereignStack\Lms\Enrollment\Domain\Enum\EnrollmentStatus;
use SovereignStack\Lms\Enrollment\Domain\Exception\InvalidEnrollmentTransitionException;
use SovereignStack\Lms\Learning\Application\Contract\CannotCompleteEmptyCourseException;
use SovereignStack\Lms\Learning\Application\Contract\IncompleteModulesException;
use SovereignStack\Lms\Learning\Domain\Enum\ProgressStatus;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Tests\Support\Fakes\FakePermissionChecker;
use SovereignStack\Lms\Tests\Unit\Enrollment\Application\ApplicationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class CompleteEnrollmentHandlerTest extends ApplicationTestCase
{
    public function testLearnerCanCompleteWhenRequiredModulesAreDone(): void
    {
        $this->publishedCourse();
        $enrollment = $this->activeEnrollment();
        $this->seedModuleProgress($enrollment->id()->value(), self::MODULE_ID, ProgressStatus::COMPLETED);

        $completionId = ($this->completeHandler())(new CompleteEnrollmentCommand(
            self::LEARNER_ID,
            $enrollment->id()->value(),
        ));

        self::assertSame($completionId->value(), $this->completions->findByEnrollment($enrollment->id()->value())?->id()->value());
        self::assertSame(EnrollmentStatus::COMPLETED, $this->enrollments->findById($enrollment->id())?->status());
        self::assertTrue($this->audit->hasEvent('enrollment.completed'));
        self::assertTrue($this->outbox->hasEvent('EnrollmentCompleted'));
    }

    public function testCompletionIsIdempotent(): void
    {
        $this->publishedCourse();
        $enrollment = $this->activeEnrollment();
        $this->seedModuleProgress($enrollment->id()->value(), self::MODULE_ID, ProgressStatus::COMPLETED);

        $handler = $this->completeHandler();
        $first = $handler(new CompleteEnrollmentCommand(self::LEARNER_ID, $enrollment->id()->value()));
        $second = $handler(new CompleteEnrollmentCommand(self::LEARNER_ID, $enrollment->id()->value()));

        self::assertSame($first, $second);
        self::assertSame(1, \count($this->completions->completions));
        self::assertSame(1, $this->auditCount('enrollment.completed'));
        self::assertSame(1, \count($this->outbox->messages));
    }

    public function testUnrelatedActorCannotCompleteAnAlreadyCompletedEnrollment(): void
    {
        $this->publishedCourse();
        $enrollment = $this->activeEnrollment();
        $this->seedModuleProgress($enrollment->id()->value(), self::MODULE_ID, ProgressStatus::COMPLETED);

        $handler = $this->completeHandler();
        $handler(new CompleteEnrollmentCommand(self::LEARNER_ID, $enrollment->id()->value()));

        $this->setPermissions(new FakePermissionChecker(['user.manage']));

        $this->expectException(ForbiddenException::class);

        ($this->completeHandler())(new CompleteEnrollmentCommand(self::OTHER_ID, $enrollment->id()->value()));
    }

    public function testEmptyCourseCannotBeCompleted(): void
    {
        $this->publishCourse([]);
        $enrollment = $this->activeEnrollment();

        $this->expectException(CannotCompleteEmptyCourseException::class);

        ($this->completeHandler())(new CompleteEnrollmentCommand(self::LEARNER_ID, $enrollment->id()->value()));
    }

    public function testIncompleteRequiredModuleIsRejected(): void
    {
        $this->publishedCourse();
        $enrollment = $this->activeEnrollment();

        $this->expectException(IncompleteModulesException::class);

        ($this->completeHandler())(new CompleteEnrollmentCommand(self::LEARNER_ID, $enrollment->id()->value()));
    }

    public function testUnrelatedActorIsDenied(): void
    {
        $this->publishedCourse();
        $enrollment = $this->activeEnrollment();
        $this->seedModuleProgress($enrollment->id()->value(), self::MODULE_ID, ProgressStatus::COMPLETED);
        $this->setPermissions(new FakePermissionChecker(['user.manage']));

        $this->expectException(ForbiddenException::class);

        ($this->completeHandler())(new CompleteEnrollmentCommand(self::OTHER_ID, $enrollment->id()->value()));
    }

    public function testCancelledEnrollmentCannotBeCompleted(): void
    {
        $this->publishedCourse();
        $enrollment = $this->activeEnrollment();
        $enrollment->cancel($this->clock->now());
        $this->enrollments->save($enrollment);

        $this->expectException(InvalidEnrollmentTransitionException::class);

        ($this->completeHandler())(new CompleteEnrollmentCommand(self::LEARNER_ID, $enrollment->id()->value()));
    }
}
