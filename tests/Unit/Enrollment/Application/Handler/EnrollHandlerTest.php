<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Enrollment\Application\Handler;

use SovereignStack\Lms\Enrollment\Application\Command\EnrollCommand;
use SovereignStack\Lms\Enrollment\Domain\Exception\AlreadyEnrolledException;
use SovereignStack\Lms\Enrollment\Domain\Exception\CourseNotPublishedException;
use SovereignStack\Lms\Enrollment\Domain\ValueObject\EnrollmentId;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Tests\Support\Fakes\FakePermissionChecker;
use SovereignStack\Lms\Tests\Unit\Enrollment\Application\ApplicationTestCase;
use SovereignStack\Lms\Tests\Unit\Enrollment\Application\Fakes\FakeMembershipChecker;

/**
 * @internal
 *
 * @coversNothing
 */
final class EnrollHandlerTest extends ApplicationTestCase
{
    public function testLearnerCanEnrollInPublishedCourse(): void
    {
        $this->publishedCourse();

        $enrollmentId = ($this->enrollHandler())(new EnrollCommand(
            self::LEARNER_ID,
            self::LEARNER_ID,
            self::COURSE_ID,
        ));

        $enrollment = $this->enrollments->findById($enrollmentId);
        self::assertNotNull($enrollment);
        self::assertSame(self::LEARNER_ID, $enrollment->learnerUserId()->value());
        self::assertSame(self::COURSE_ID, $enrollment->courseId());
        self::assertSame(self::VERSION_ID, $enrollment->courseVersionId());
        self::assertSame(self::ORGANISATION_ID, $enrollment->organisationId()->value());
        self::assertSame(1, $this->transactions->calls);
        self::assertTrue($this->audit->hasEvent('enrollment.created'));
        self::assertTrue($this->outbox->hasEvent('EnrollmentCreated'));
    }

    public function testDuplicateActiveEnrollmentIsRejected(): void
    {
        $this->publishedCourse();
        $this->enrollments->enforceUniqueActive = true;
        $handler = $this->enrollHandler();
        $handler(new EnrollCommand(self::LEARNER_ID, self::LEARNER_ID, self::COURSE_ID));

        $this->expectException(AlreadyEnrolledException::class);

        $handler(new EnrollCommand(self::LEARNER_ID, self::LEARNER_ID, self::COURSE_ID));
    }

    public function testEnrollmentRequiresPublishedCourse(): void
    {
        $this->expectException(CourseNotPublishedException::class);

        ($this->enrollHandler())(new EnrollCommand(self::LEARNER_ID, self::LEARNER_ID, self::COURSE_ID));
    }

    public function testLearnerMustBeAnActiveMember(): void
    {
        $this->publishedCourse();
        $this->memberships = new FakeMembershipChecker([]);
        $this->setPermissions(new FakePermissionChecker(['user.manage']));

        $this->expectException(ForbiddenException::class);

        ($this->enrollHandler())(new EnrollCommand(self::LEARNER_ID, self::LEARNER_ID, self::COURSE_ID));
    }

    public function testNonSelfActorNeedsUserManage(): void
    {
        $this->publishedCourse();
        $this->memberships = new FakeMembershipChecker([self::LEARNER_ID]);
        $this->setPermissions(new FakePermissionChecker(['user.manage']));

        $this->expectException(ForbiddenException::class);

        ($this->enrollHandler())(new EnrollCommand(self::MANAGER_ID, self::LEARNER_ID, self::COURSE_ID));
    }

    public function testAdminCanEnrollAnotherLearnerWithoutMembership(): void
    {
        $this->publishedCourse();
        $this->memberships = new FakeMembershipChecker([]);
        $this->setPermissions(new FakePermissionChecker());

        $enrollmentId = ($this->enrollHandler())(new EnrollCommand(
            self::ADMIN_ID,
            self::LEARNER_ID,
            self::COURSE_ID,
        ));

        self::assertInstanceOf(EnrollmentId::class, $enrollmentId);
        self::assertSame(self::LEARNER_ID, $this->enrollments->findById($enrollmentId)?->learnerUserId()->value());
    }
}
