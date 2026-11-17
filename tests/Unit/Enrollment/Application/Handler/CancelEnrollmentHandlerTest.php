<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Enrollment\Application\Handler;

use SovereignStack\Lms\Enrollment\Application\Command\CancelEnrollmentCommand;
use SovereignStack\Lms\Enrollment\Domain\Enum\EnrollmentStatus;
use SovereignStack\Lms\Enrollment\Domain\Exception\EnrollmentNotFoundException;
use SovereignStack\Lms\Enrollment\Domain\Exception\InvalidEnrollmentTransitionException;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Tests\Support\Fakes\FakePermissionChecker;
use SovereignStack\Lms\Tests\Unit\Enrollment\Application\ApplicationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class CancelEnrollmentHandlerTest extends ApplicationTestCase
{
    public function testLearnerCanCancelOwnEnrollment(): void
    {
        $enrollment = $this->activeEnrollment();

        ($this->cancelHandler())(new CancelEnrollmentCommand(self::LEARNER_ID, $enrollment->id()->value()));

        $stored = $this->enrollments->findById($enrollment->id());
        self::assertNotNull($stored);
        self::assertSame(EnrollmentStatus::CANCELLED, $stored->status());
        self::assertSame($this->clock->now(), $stored->cancelledAt());
        self::assertTrue($this->audit->hasEvent('enrollment.cancelled'));
        self::assertTrue($this->outbox->hasEvent('EnrollmentCancelled'));
    }

    public function testManagerWithViewAnyCanCancelAnotherLearnersEnrollment(): void
    {
        $enrollment = $this->activeEnrollment();

        ($this->cancelHandler())(new CancelEnrollmentCommand(self::MANAGER_ID, $enrollment->id()->value()));

        self::assertSame(EnrollmentStatus::CANCELLED, $this->enrollments->findById($enrollment->id())?->status());
    }

    public function testUnrelatedActorIsDenied(): void
    {
        $enrollment = $this->activeEnrollment();
        $this->setPermissions(new FakePermissionChecker(['enrollment.view.any']));

        $this->expectException(ForbiddenException::class);

        ($this->cancelHandler())(new CancelEnrollmentCommand(self::OTHER_ID, $enrollment->id()->value()));
    }

    public function testCancelledEnrollmentCannotBeCancelledAgain(): void
    {
        $enrollment = $this->activeEnrollment();
        $handler = $this->cancelHandler();
        $handler(new CancelEnrollmentCommand(self::LEARNER_ID, $enrollment->id()->value()));

        $this->expectException(InvalidEnrollmentTransitionException::class);

        $handler(new CancelEnrollmentCommand(self::LEARNER_ID, $enrollment->id()->value()));
    }

    public function testMissingEnrollmentIsNotFound(): void
    {
        $this->expectException(EnrollmentNotFoundException::class);

        ($this->cancelHandler())(new CancelEnrollmentCommand(
            self::LEARNER_ID,
            '01900000-0000-7000-8000-000000000098',
        ));
    }
}
