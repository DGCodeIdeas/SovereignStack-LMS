<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Enrollment\Application;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Course\Application\Contract\PublishedCourseVersion;
use SovereignStack\Lms\Course\Application\Contract\PublishedLesson;
use SovereignStack\Lms\Course\Application\Contract\PublishedModule;
use SovereignStack\Lms\Enrollment\Application\Contract\CompletionErrorsAdapter;
use SovereignStack\Lms\Enrollment\Application\Contract\LearningProgressAdapter;
use SovereignStack\Lms\Enrollment\Application\Contract\LessonProgressRecorderAdapter;
use SovereignStack\Lms\Enrollment\Application\Contract\PermissionCheckerAdapter;
use SovereignStack\Lms\Enrollment\Application\Contract\ProgressStatusAdapter;
use SovereignStack\Lms\Enrollment\Application\Handler\CancelEnrollmentHandler;
use SovereignStack\Lms\Enrollment\Application\Handler\CompleteEnrollmentHandler;
use SovereignStack\Lms\Enrollment\Application\Handler\EnrollHandler;
use SovereignStack\Lms\Enrollment\Application\Handler\RecordLessonProgressHandler;
use SovereignStack\Lms\Enrollment\Application\Security\EnrollmentAccessPolicy;
use SovereignStack\Lms\Enrollment\Domain\Entity\CourseCompletion;
use SovereignStack\Lms\Enrollment\Domain\Entity\Enrollment;
use SovereignStack\Lms\Enrollment\Domain\ValueObject\CourseCompletionId;
use SovereignStack\Lms\Enrollment\Domain\ValueObject\EnrollmentId;
use SovereignStack\Lms\Identity\Application\Contract\PermissionCheckerInterface;
use SovereignStack\Lms\Learning\Application\Contract\RequiredModulesAdapter;
use SovereignStack\Lms\Learning\Application\LessonProgressRecorder;
use SovereignStack\Lms\Learning\Application\Progress;
use SovereignStack\Lms\Learning\Domain\Entity\LessonProgress;
use SovereignStack\Lms\Learning\Domain\Entity\ModuleProgress;
use SovereignStack\Lms\Learning\Domain\Enum\ProgressStatus;
use SovereignStack\Lms\Learning\Domain\ValueObject\LessonProgressId;
use SovereignStack\Lms\Learning\Domain\ValueObject\ModuleProgressId;
use SovereignStack\Lms\Learning\Infrastructure\LearningProgress;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use SovereignStack\Lms\Tests\Support\Fakes\FakePermissionChecker;
use SovereignStack\Lms\Tests\Support\Fakes\FakeTransactionManager;
use SovereignStack\Lms\Tests\Support\Fakes\FixedClock;
use SovereignStack\Lms\Tests\Support\Fakes\RecordingAuditLogger;
use SovereignStack\Lms\Tests\Support\Fakes\SequentialUuidGenerator;
use SovereignStack\Lms\Tests\Unit\Enrollment\Application\Fakes\FakeCourseContentProvider;
use SovereignStack\Lms\Tests\Unit\Enrollment\Application\Fakes\FakeMembershipChecker;
use SovereignStack\Lms\Tests\Unit\Enrollment\Application\Fakes\InMemoryCourseCompletionRepository;
use SovereignStack\Lms\Tests\Unit\Enrollment\Application\Fakes\InMemoryEnrollmentRepository;
use SovereignStack\Lms\Tests\Unit\Enrollment\Application\Fakes\InMemoryLessonProgressRepository;
use SovereignStack\Lms\Tests\Unit\Enrollment\Application\Fakes\InMemoryModuleProgressRepository;
use SovereignStack\Lms\Tests\Unit\Enrollment\Application\Fakes\RecordingOutboxWriter;

abstract class ApplicationTestCase extends TestCase
{
    protected const ORGANISATION_ID = '01900000-0000-7000-8000-000000000001';
    protected const LEARNER_ID = '01900000-0000-7000-8000-000000000002';
    protected const ADMIN_ID = '01900000-0000-7000-8000-000000000003';
    protected const MANAGER_ID = '01900000-0000-7000-8000-000000000004';
    protected const OTHER_ID = '01900000-0000-7000-8000-000000000005';
    protected const COURSE_ID = '01900000-0000-7000-8000-000000000010';
    protected const VERSION_ID = '01900000-0000-7000-8000-000000000011';
    protected const MODULE_ID = '01900000-0000-7000-8000-000000000012';
    protected const LESSON_ID = '01900000-0000-7000-8000-000000000013';

    protected FixedClock $clock;
    protected SequentialUuidGenerator $uuid;
    protected RecordingAuditLogger $audit;
    protected RecordingOutboxWriter $outbox;
    protected FakeTransactionManager $transactions;
    protected PermissionCheckerInterface $permissions;
    protected InMemoryEnrollmentRepository $enrollments;
    protected InMemoryCourseCompletionRepository $completions;
    protected InMemoryLessonProgressRepository $lessonProgress;
    protected InMemoryModuleProgressRepository $moduleProgress;
    protected FakeCourseContentProvider $content;
    protected FakeMembershipChecker $memberships;
    protected EnrollmentAccessPolicy $policy;

    protected function setUp(): void
    {
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-11-16T10:00:00+00:00'));
        $this->uuid = new SequentialUuidGenerator();
        $this->audit = new RecordingAuditLogger();
        $this->outbox = new RecordingOutboxWriter();
        $this->transactions = new FakeTransactionManager();
        $this->permissions = new FakePermissionChecker();
        $this->enrollments = new InMemoryEnrollmentRepository();
        $this->completions = new InMemoryCourseCompletionRepository();
        $this->lessonProgress = new InMemoryLessonProgressRepository();
        $this->moduleProgress = new InMemoryModuleProgressRepository();
        $this->content = new FakeCourseContentProvider();
        $this->memberships = new FakeMembershipChecker([]);
        $this->policy = new EnrollmentAccessPolicy(new PermissionCheckerAdapter($this->permissions));
    }

    /**
     * @param list<PublishedModule> $modules
     */
    protected function publishCourse(
        array $modules,
        string $courseId = self::COURSE_ID,
        string $versionId = self::VERSION_ID,
    ): PublishedCourseVersion {
        return $this->content->publish($courseId, $versionId, self::ORGANISATION_ID, 'Course', $modules);
    }

    /**
     * @param list<PublishedLesson> $lessons
     */
    protected function module(
        string $id,
        string $title = 'Module',
        bool $isRequired = true,
        array $lessons = [],
    ): PublishedModule {
        return new PublishedModule($id, $title, $isRequired, $lessons);
    }

    protected function lesson(string $id, string $title = 'Lesson'): PublishedLesson
    {
        return new PublishedLesson($id, $title);
    }

    protected function publishedCourse(): PublishedCourseVersion
    {
        return $this->publishCourse([
            $this->module(self::MODULE_ID, 'Module', true, [$this->lesson(self::LESSON_ID)]),
        ]);
    }

    protected function activeEnrollment(
        string $id = '01900000-0000-7000-8000-000000000030',
        string $learnerId = self::LEARNER_ID,
        string $courseId = self::COURSE_ID,
        string $versionId = self::VERSION_ID,
    ): Enrollment {
        $enrollment = Enrollment::create(
            EnrollmentId::fromString($id),
            OrganisationIdReference::fromString(self::ORGANISATION_ID),
            UserIdReference::fromString($learnerId),
            $courseId,
            $versionId,
            $this->clock->now(),
        );

        $this->enrollments->save($enrollment);

        return $enrollment;
    }

    protected function seedModuleProgress(string $enrollmentId, string $moduleId, ProgressStatus $status): ModuleProgress
    {
        $progress = ModuleProgress::create(
            ModuleProgressId::fromString($this->uuid->generate()),
            $enrollmentId,
            $moduleId,
            $this->clock->now(),
        );

        if (ProgressStatus::IN_PROGRESS === $status) {
            $progress->markStarted($this->clock->now());
        } elseif (ProgressStatus::COMPLETED === $status) {
            $progress->markCompleted($this->clock->now());
        }

        $this->moduleProgress->save($progress);

        return $progress;
    }

    protected function seedLessonProgress(
        string $enrollmentId,
        string $lessonId,
        string $moduleId,
        ProgressStatus $status,
    ): LessonProgress {
        $progress = LessonProgress::create(
            LessonProgressId::fromString($this->uuid->generate()),
            $enrollmentId,
            $lessonId,
            $this->clock->now(),
        );

        if (ProgressStatus::IN_PROGRESS === $status) {
            $progress->markStarted($this->clock->now());
        } elseif (ProgressStatus::COMPLETED === $status) {
            $progress->markCompleted($this->clock->now());
        }

        $this->lessonProgress->lessonModules[$lessonId] = $moduleId;
        $this->lessonProgress->save($progress);

        return $progress;
    }

    protected function seedCompletion(Enrollment $enrollment): CourseCompletion
    {
        $completion = CourseCompletion::record(
            CourseCompletionId::fromString($this->uuid->generate()),
            $enrollment->id()->value(),
            $enrollment->learnerUserId(),
            $enrollment->courseId(),
            $enrollment->courseVersionId(),
            $this->clock->now(),
        );

        $this->completions->save($completion);

        return $completion;
    }

    protected function enrollHandler(): EnrollHandler
    {
        return new EnrollHandler(
            $this->policy,
            $this->content,
            $this->memberships,
            $this->enrollments,
            $this->audit,
            $this->outbox,
            $this->transactions,
            $this->uuid,
            $this->clock,
        );
    }

    protected function cancelHandler(): CancelEnrollmentHandler
    {
        return new CancelEnrollmentHandler(
            $this->policy,
            $this->enrollments,
            $this->audit,
            $this->outbox,
            $this->transactions,
            $this->clock,
        );
    }

    protected function recordProgressHandler(): RecordLessonProgressHandler
    {
        return new RecordLessonProgressHandler(
            $this->policy,
            $this->content,
            new LessonProgressRecorderAdapter(
                new LessonProgressRecorder(
                    $this->lessonProgress,
                    $this->moduleProgress,
                    $this->audit,
                    $this->uuid,
                ),
            ),
            $this->enrollments,
            $this->transactions,
            $this->clock,
            new ProgressStatusAdapter(new Progress()),
        );
    }

    protected function completeHandler(): CompleteEnrollmentHandler
    {
        return new CompleteEnrollmentHandler(
            $this->policy,
            $this->content,
            new CompletionErrorsAdapter(),
            new LearningProgressAdapter(
                new LearningProgress(
                    new RequiredModulesAdapter($this->content),
                    $this->lessonProgress,
                    $this->moduleProgress,
                ),
            ),
            $this->enrollments,
            $this->completions,
            $this->audit,
            $this->outbox,
            $this->transactions,
            $this->uuid,
            $this->clock,
        );
    }

    protected function setPermissions(PermissionCheckerInterface $permissions): void
    {
        $this->permissions = $permissions;
        $this->policy = new EnrollmentAccessPolicy(new PermissionCheckerAdapter($permissions));
    }

    protected function auditCount(string $eventType): int
    {
        $count = 0;

        foreach ($this->audit->records as $record) {
            if ($record['eventType'] === $eventType) {
                ++$count;
            }
        }

        return $count;
    }
}
