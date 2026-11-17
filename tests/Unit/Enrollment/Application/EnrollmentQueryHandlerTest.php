<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Enrollment\Application;

use SovereignStack\Lms\Enrollment\Application\Contract\LearningProgressAdapter;
use SovereignStack\Lms\Enrollment\Application\Handler\GetEnrollmentHandler;
use SovereignStack\Lms\Enrollment\Application\Handler\ListEnrollmentsHandler;
use SovereignStack\Lms\Enrollment\Application\Query\GetEnrollmentQuery;
use SovereignStack\Lms\Enrollment\Application\Query\ListEnrollmentsQuery;
use SovereignStack\Lms\Enrollment\Domain\Enum\EnrollmentStatus;
use SovereignStack\Lms\Learning\Application\Contract\RequiredModulesAdapter;
use SovereignStack\Lms\Learning\Domain\Enum\ProgressStatus;
use SovereignStack\Lms\Learning\Infrastructure\LearningProgress;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Tests\Support\Fakes\FakePermissionChecker;

/**
 * @internal
 *
 * @coversNothing
 */
final class EnrollmentQueryHandlerTest extends ApplicationTestCase
{
    public function testListReturnsOnlyTheActorsEnrollments(): void
    {
        $this->activeEnrollment();
        $this->activeEnrollment('01900000-0000-7000-8000-000000000031', self::OTHER_ID);

        $result = (new ListEnrollmentsHandler($this->enrollments))(new ListEnrollmentsQuery(self::LEARNER_ID));

        self::assertCount(1, $result);
        self::assertSame(EnrollmentStatus::ACTIVE->value, $result[0]['status']);
        self::assertSame(self::COURSE_ID, $result[0]['courseId']);
        self::assertSame(self::VERSION_ID, $result[0]['courseVersionId']);
    }

    public function testLearnerCanReadOwnEnrollmentWithProgress(): void
    {
        $enrollment = $this->activeEnrollment();
        $this->seedModuleProgress($enrollment->id()->value(), self::MODULE_ID, ProgressStatus::COMPLETED);
        $this->seedLessonProgress(
            $enrollment->id()->value(),
            self::LESSON_ID,
            self::MODULE_ID,
            ProgressStatus::COMPLETED,
        );
        $this->seedCompletion($enrollment);

        $result = $this->getHandler()(
            new GetEnrollmentQuery(self::LEARNER_ID, $enrollment->id()->value()),
        );

        self::assertSame($enrollment->id()->value(), $result['id']);
        self::assertCount(1, $result['lessonProgress']);
        self::assertSame(ProgressStatus::COMPLETED->value, $result['lessonProgress'][0]['status']);
        self::assertCount(1, $result['moduleProgress']);
        self::assertSame(ProgressStatus::COMPLETED->value, $result['moduleProgress'][0]['status']);
        self::assertNotNull($result['completion']);
    }

    public function testManagerCanReadAnotherLearnersEnrollment(): void
    {
        $enrollment = $this->activeEnrollment();

        $result = $this->getHandler()(
            new GetEnrollmentQuery(self::MANAGER_ID, $enrollment->id()->value()),
        );

        self::assertSame($enrollment->id()->value(), $result['id']);
    }

    public function testUnrelatedActorIsDenied(): void
    {
        $enrollment = $this->activeEnrollment();
        $this->setPermissions(new FakePermissionChecker(['enrollment.view.any']));

        $this->expectException(ForbiddenException::class);

        $this->getHandler()(
            new GetEnrollmentQuery(self::OTHER_ID, $enrollment->id()->value()),
        );
    }

    private function getHandler(): GetEnrollmentHandler
    {
        return new GetEnrollmentHandler(
            $this->policy,
            $this->enrollments,
            new LearningProgressAdapter(
                new LearningProgress(
                    new RequiredModulesAdapter($this->content),
                    $this->lessonProgress,
                    $this->moduleProgress,
                ),
            ),
            $this->completions,
        );
    }
}
