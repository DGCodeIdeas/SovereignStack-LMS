<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Functional;

use SovereignStack\Lms\Course\Domain\Entity\Course;
use SovereignStack\Lms\Course\Domain\Entity\CourseModule;
use SovereignStack\Lms\Course\Domain\Entity\CourseVersion;
use SovereignStack\Lms\Course\Domain\Entity\Lesson;
use SovereignStack\Lms\Course\Domain\Entity\LessonContent;
use SovereignStack\Lms\Course\Domain\Enum\CourseVisibility;
use SovereignStack\Lms\Course\Domain\Repository\CourseModuleRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseVersionRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\LessonContentRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\LessonRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseModuleId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;
use SovereignStack\Lms\Course\Domain\ValueObject\LessonId;
use SovereignStack\Lms\Identity\Application\Contract\PasswordHasherInterface;
use SovereignStack\Lms\Identity\Domain\Entity\User;
use SovereignStack\Lms\Identity\Domain\Repository\RoleRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\Repository\UserRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Identity\Domain\ValueObject\RoleIdentifier;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Learning\Domain\Exception\ProgressConflictException;
use SovereignStack\Lms\Learning\Domain\Repository\LessonProgressRepositoryInterface;
use SovereignStack\Lms\Organisation\Domain\Entity\Membership;
use SovereignStack\Lms\Organisation\Domain\Entity\Organisation;
use SovereignStack\Lms\Organisation\Domain\Repository\MembershipRepositoryInterface;
use SovereignStack\Lms\Organisation\Domain\Repository\OrganisationRepositoryInterface;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DatabaseValue;
use SovereignStack\Lms\Tests\Functional\Fixtures\DeterministicTokenGenerator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class EnrollmentLearningFlowTest extends DatabaseWebTestCase
{
    private const PASSWORD = 'CorrectHorseBatteryStaple1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncate(
            'outbox_messages',
            'course_completions',
            'lesson_progress',
            'module_progress',
            'enrollments',
            'course_instructors',
            'course_category_assignments',
            'course_categories',
            'lesson_contents',
            'lessons',
            'course_modules',
            'course_versions',
            'courses',
            'user_tokens',
            'organisation_memberships',
            'user_roles',
            'user_profiles',
            'audit_events',
            'users',
            'organisations',
        );

        DeterministicTokenGenerator::reset();
    }

    public function testLearnerEnrollsInPublishedCourseAndDuplicateActiveEnrollmentIsRejected(): void
    {
        $context = $this->seedPublishedCourse('enroll-one', true, 1, true);
        $client = $this->client();
        $this->login($client, 'enroll-one-learner@example.com');

        $enrollmentId = $this->enroll($client, $context['courseId']);

        $enrollment = $this->activeEnrollment($context['learnerId'], $context['courseId']);
        self::assertNotNull($enrollment);
        self::assertSame('active', $enrollment['status']);
        self::assertSame($context['versionId'], $enrollment['course_version_id']);
        self::assertSame($context['organisationId'], $enrollment['organisation_id']);
        self::assertSame($enrollmentId, $enrollment['id']);

        $client->request('GET', '/enrollments');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('My enrollments', $this->content($client));

        $client->request('GET', '/enrollments/'.$enrollmentId);
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $content = $this->content($client);
        self::assertStringContainsString('Enrollment details', $content);
        self::assertStringContainsString('Lesson progress', $content);
        self::assertStringNotContainsString(self::PASSWORD, $content);

        $token = $this->csrfToken($client, 'enroll_course');
        $client->request('POST', '/courses/'.$context['courseId'].'/enroll', [
            '_csrf_token' => $token,
        ]);

        self::assertTrue($client->getResponse()->isRedirect());
        self::assertSame(1, $this->countRows('enrollments'));
        self::assertSame(1, $this->countRowsForLearner('enrollments', $context['learnerId']));
    }

    public function testCancelThenReenrollCreatesNewEnrollmentAndKeepsHistory(): void
    {
        $context = $this->seedPublishedCourse('reenroll', true, 1, false);
        $client = $this->client();
        $this->login($client, 'reenroll-learner@example.com');

        $firstId = $this->enroll($client, $context['courseId']);

        $token = $this->csrfToken($client, 'cancel_enrollment');
        $client->request('POST', '/enrollments/'.$firstId.'/cancel', ['_csrf_token' => $token]);
        self::assertTrue($client->getResponse()->isRedirect());

        $first = $this->enrollmentRow($firstId);
        self::assertSame('cancelled', $first['status']);
        self::assertNotNull($first['cancelled_at']);

        $secondVersionId = $this->publishAdditionalVersion(
            $context['courseId'],
            $context['adminId'],
            2,
        );
        self::assertNotSame($context['versionId'], $secondVersionId);

        $secondId = $this->enroll($client, $context['courseId']);
        self::assertNotSame($firstId, $secondId);

        self::assertSame(2, $this->countRows('enrollments'));
        self::assertSame('cancelled', $this->enrollmentRow($firstId)['status']);

        $second = $this->enrollmentRow($secondId);
        self::assertSame('active', $second['status']);
        self::assertSame($secondVersionId, $second['course_version_id']);
        self::assertSame(1, $this->countRowsForLearnerAndStatus($context['learnerId'], 'active'));
    }

    public function testRequiredModuleProgressIsIdempotentAndRejectsBackwardsTransitions(): void
    {
        $context = $this->seedPublishedCourse('progress', true, 2, true);
        $client = $this->client();
        $this->login($client, 'progress-learner@example.com');

        $enrollmentId = $this->enroll($client, $context['courseId']);
        self::assertCount(2, $context['requiredLessonIds']);

        foreach ($context['requiredLessonIds'] as $lessonId) {
            $this->recordProgress($client, $enrollmentId, $lessonId, 'completed');
        }

        self::assertSame(2, $this->countRows('lesson_progress'));

        $moduleStatus = $this->moduleProgressStatus($enrollmentId, $this->requiredModuleId($context));
        self::assertSame('completed', $moduleStatus);

        $before = $this->lessonProgressRow($enrollmentId, $context['requiredLessonIds'][0]);
        $this->recordProgress($client, $enrollmentId, $context['requiredLessonIds'][0], 'completed');
        $after = $this->lessonProgressRow($enrollmentId, $context['requiredLessonIds'][0]);
        self::assertSame($before['version'], $after['version']);
        self::assertSame(2, $this->countRows('lesson_progress'));

        $this->recordProgress($client, $enrollmentId, $context['requiredLessonIds'][0], 'in_progress');
        $backwards = $this->lessonProgressRow($enrollmentId, $context['requiredLessonIds'][0]);
        self::assertSame('completed', $backwards['status']);
        self::assertSame($before['version'], $backwards['version']);
        self::assertSame(2, $this->countRows('lesson_progress'));
    }

    public function testCompletionRequiresRequiredModuleAndIsIdempotent(): void
    {
        $context = $this->seedPublishedCourse('complete', true, 1, true);
        $client = $this->client();
        $this->login($client, 'complete-learner@example.com');

        $enrollmentId = $this->enroll($client, $context['courseId']);

        $token = $this->csrfToken($client, 'complete_enrollment');
        $client->request('POST', '/enrollments/'.$enrollmentId.'/complete', ['_csrf_token' => $token]);
        self::assertTrue($client->getResponse()->isRedirect());

        self::assertSame('active', $this->enrollmentRow($enrollmentId)['status']);
        self::assertSame(0, $this->countRows('course_completions'));

        $this->recordProgress($client, $enrollmentId, $context['requiredLessonIds'][0], 'completed');

        $token = $this->csrfToken($client, 'complete_enrollment');
        $client->request('POST', '/enrollments/'.$enrollmentId.'/complete', ['_csrf_token' => $token]);
        self::assertTrue($client->getResponse()->isRedirect());

        self::assertSame('completed', $this->enrollmentRow($enrollmentId)['status']);
        self::assertSame(1, $this->countRows('course_completions'));

        $completion = $this->connection()->fetchAssociative(
            'SELECT * FROM course_completions WHERE enrollment_id = :enrollment_id',
            ['enrollment_id' => $enrollmentId],
        );
        self::assertIsArray($completion);
        self::assertSame($context['versionId'], $completion['course_version_id']);

        $token = $this->csrfToken($client, 'complete_enrollment');
        $client->request('POST', '/enrollments/'.$enrollmentId.'/complete', ['_csrf_token' => $token]);
        self::assertTrue($client->getResponse()->isRedirect());

        self::assertSame(1, $this->countRows('course_completions'));
        self::assertSame(
            $completion['id'],
            $this->connection()->fetchOne(
                'SELECT id FROM course_completions WHERE enrollment_id = :enrollment_id',
                ['enrollment_id' => $enrollmentId],
            ),
        );
        self::assertSame('completed', $this->enrollmentRow($enrollmentId)['status']);
    }

    public function testMultiLessonRequiredModuleRequiresEveryLessonForCompletion(): void
    {
        $context = $this->seedPublishedCourse('integrity-multilesson', true, 2, false);
        $client = $this->client();
        $this->login($client, 'integrity-multilesson-learner@example.com');

        $enrollmentId = $this->enroll($client, $context['courseId']);
        self::assertCount(2, $context['requiredLessonIds']);

        $this->recordProgress($client, $enrollmentId, $context['requiredLessonIds'][0], 'completed');

        self::assertSame(
            'in_progress',
            $this->moduleProgressStatus($enrollmentId, $this->requiredModuleId($context)),
        );
        self::assertSame(1, $this->countRows('lesson_progress'));
        self::assertSame(0, $this->countRows('course_completions'));

        $token = $this->csrfToken($client, 'complete_enrollment');
        $client->request('POST', '/enrollments/'.$enrollmentId.'/complete', ['_csrf_token' => $token]);
        self::assertTrue($client->getResponse()->isRedirect());
        self::assertSame('active', $this->enrollmentRow($enrollmentId)['status']);
        self::assertSame(0, $this->countRows('course_completions'));

        $this->recordProgress($client, $enrollmentId, $context['requiredLessonIds'][1], 'completed');

        self::assertSame(
            'completed',
            $this->moduleProgressStatus($enrollmentId, $this->requiredModuleId($context)),
        );
        self::assertSame(2, $this->countRows('lesson_progress'));

        $token = $this->csrfToken($client, 'complete_enrollment');
        $client->request('POST', '/enrollments/'.$enrollmentId.'/complete', ['_csrf_token' => $token]);
        self::assertTrue($client->getResponse()->isRedirect());

        self::assertSame('completed', $this->enrollmentRow($enrollmentId)['status']);
        self::assertSame(1, $this->countRows('course_completions'));

        $completionId = $this->connection()->fetchOne(
            'SELECT id FROM course_completions WHERE enrollment_id = :enrollment_id',
            ['enrollment_id' => $enrollmentId],
        );
        self::assertIsString($completionId);

        $token = $this->csrfToken($client, 'complete_enrollment');
        $client->request('POST', '/enrollments/'.$enrollmentId.'/complete', ['_csrf_token' => $token]);
        self::assertTrue($client->getResponse()->isRedirect());

        self::assertSame(1, $this->countRows('course_completions'));
        self::assertSame(
            $completionId,
            $this->connection()->fetchOne(
                'SELECT id FROM course_completions WHERE enrollment_id = :enrollment_id',
                ['enrollment_id' => $enrollmentId],
            ),
        );
    }

    public function testZeroLessonRequiredModuleCannotBeCompleted(): void
    {
        $context = $this->seedPublishedCourse('integrity-zero-lesson', true, 0, false);
        self::assertNotNull($context['requiredModuleId']);
        self::assertSame([], $context['requiredLessonIds']);

        $client = $this->client();
        $this->login($client, 'integrity-zero-lesson-learner@example.com');

        $enrollmentId = $this->enroll($client, $context['courseId']);

        $token = $this->csrfToken($client, 'complete_enrollment');
        $client->request('POST', '/enrollments/'.$enrollmentId.'/complete', ['_csrf_token' => $token]);
        self::assertTrue($client->getResponse()->isRedirect());

        self::assertSame('active', $this->enrollmentRow($enrollmentId)['status']);
        self::assertSame(0, $this->countRows('course_completions'));
        self::assertSame(0, $this->countRows('module_progress'));
    }

    public function testIncompleteOptionalModuleDoesNotBlockCompletion(): void
    {
        $context = $this->seedPublishedCourse('integrity-optional', true, 1, true);
        self::assertNotNull($context['optionalModuleId']);
        self::assertNotEmpty($context['optionalLessonIds']);

        $client = $this->client();
        $this->login($client, 'integrity-optional-learner@example.com');

        $enrollmentId = $this->enroll($client, $context['courseId']);
        $this->recordProgress($client, $enrollmentId, $context['requiredLessonIds'][0], 'completed');

        $token = $this->csrfToken($client, 'complete_enrollment');
        $client->request('POST', '/enrollments/'.$enrollmentId.'/complete', ['_csrf_token' => $token]);
        self::assertTrue($client->getResponse()->isRedirect());

        self::assertSame('completed', $this->enrollmentRow($enrollmentId)['status']);
        self::assertSame(1, $this->countRows('course_completions'));
        self::assertSame(1, $this->countRows('module_progress'));
        self::assertSame(
            'completed',
            $this->moduleProgressStatus($enrollmentId, $this->requiredModuleId($context)),
        );
        self::assertFalse($this->connection()->fetchOne(
            'SELECT status FROM module_progress WHERE enrollment_id = :enrollment AND course_module_id = :module',
            ['enrollment' => $enrollmentId, 'module' => $context['optionalModuleId']],
        ));
    }

    public function testConcurrentLessonProgressSavesDoNotLoseUpdates(): void
    {
        $context = $this->seedPublishedCourse('integrity-concurrent', true, 1, false);
        $client = $this->client();
        $this->login($client, 'integrity-concurrent-learner@example.com');

        $enrollmentId = $this->enroll($client, $context['courseId']);
        $lessonId = $context['requiredLessonIds'][0];
        $this->recordProgress($client, $enrollmentId, $lessonId, 'in_progress');

        $repository = $this->service(LessonProgressRepositoryInterface::class);
        $first = $repository->findByEnrollmentAndLesson($enrollmentId, $lessonId);
        $second = $repository->findByEnrollmentAndLesson($enrollmentId, $lessonId);
        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame($first->version(), $second->version());

        $first->markCompleted(new \DateTimeImmutable('2026-11-17T06:00:00+00:00'));
        $repository->save($first);

        $second->markCompleted(new \DateTimeImmutable('2026-11-17T06:01:00+00:00'));

        try {
            $repository->save($second);
            self::fail('A stale concurrent save must raise a conflict instead of overwriting.');
        } catch (ProgressConflictException) {
        }

        $row = $this->lessonProgressRow($enrollmentId, $lessonId);
        self::assertSame('completed', $row['status']);
        self::assertSame(2, DatabaseValue::int($row, 'version'));
        self::assertSame(1, $this->countRows('lesson_progress'));
    }

    public function testEmptyRequiredModuleCourseCannotBeCompleted(): void
    {
        $context = $this->seedPublishedCourse('empty-required', false, 0, true);
        $client = $this->client();
        $this->login($client, 'empty-required-learner@example.com');

        $enrollmentId = $this->enroll($client, $context['courseId']);

        $token = $this->csrfToken($client, 'complete_enrollment');
        $client->request('POST', '/enrollments/'.$enrollmentId.'/complete', ['_csrf_token' => $token]);
        self::assertTrue($client->getResponse()->isRedirect());

        self::assertSame('active', $this->enrollmentRow($enrollmentId)['status']);
        self::assertSame(0, $this->countRows('course_completions'));
        self::assertSame(0, $this->countRows('module_progress'));
    }

    public function testCrossOrganisationEnrollmentAndForeignEnrollmentAccessAreDenied(): void
    {
        $context = $this->seedPublishedCourse('isolation', true, 1, false);
        $otherLearnerId = $this->createVerifiedUser('isolation-other@example.com', 'lms:learner');
        $this->addMembershipDirect($context['organisationId'], $otherLearnerId);

        $outsiderId = $this->createVerifiedUser('isolation-outsider@example.com', 'lms:learner');
        $otherOrganisationId = $this->createOrganisationDirect('Isolation Other', 'isolation-other-org');
        $this->addMembershipDirect($otherOrganisationId, $outsiderId);

        $client = $this->client();
        $this->login($client, 'isolation-outsider@example.com');
        $token = $this->csrfToken($client, 'enroll_course');
        $client->request('POST', '/courses/'.$context['courseId'].'/enroll', ['_csrf_token' => $token]);
        self::assertSame(403, $client->getResponse()->getStatusCode());
        self::assertSame(0, $this->countRows('enrollments'));

        $ownerClient = $this->client();
        $this->login($ownerClient, 'isolation-learner@example.com');
        $enrollmentId = $this->enroll($ownerClient, $context['courseId']);

        $otherClient = $this->client();
        $this->login($otherClient, 'isolation-other@example.com');
        $otherClient->request('GET', '/enrollments/'.$enrollmentId);
        self::assertSame(403, $otherClient->getResponse()->getStatusCode());

        $ownerClient->request('GET', '/enrollments/'.$enrollmentId);
        self::assertSame(200, $ownerClient->getResponse()->getStatusCode());
    }

    public function testCsrfIsRequiredForEnrollmentMutations(): void
    {
        $context = $this->seedPublishedCourse('csrf', true, 1, false);
        $client = $this->client();
        $this->login($client, 'csrf-learner@example.com');

        $client->request('POST', '/courses/'.$context['courseId'].'/enroll');
        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertSame(0, $this->countRows('enrollments'));

        $enrollmentId = $this->enroll($client, $context['courseId']);

        $client->request('POST', '/enrollments/'.$enrollmentId.'/lessons/'.$context['requiredLessonIds'][0].'/progress', [
            'status' => 'completed',
        ]);
        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertSame(0, $this->countRows('lesson_progress'));

        $client->request('POST', '/enrollments/'.$enrollmentId.'/complete');
        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertSame(0, $this->countRows('course_completions'));
        self::assertSame('active', $this->enrollmentRow($enrollmentId)['status']);

        $client->request('POST', '/enrollments/'.$enrollmentId.'/cancel');
        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertSame('active', $this->enrollmentRow($enrollmentId)['status']);
    }

    public function testSuccessfulMutationsWriteOutboxEventsWithoutSecrets(): void
    {
        $context = $this->seedPublishedCourse('outbox', true, 1, false);
        $client = $this->client();
        $this->login($client, 'outbox-learner@example.com');

        $firstId = $this->enroll($client, $context['courseId']);

        $token = $this->csrfToken($client, 'cancel_enrollment');
        $client->request('POST', '/enrollments/'.$firstId.'/cancel', ['_csrf_token' => $token]);
        self::assertTrue($client->getResponse()->isRedirect());

        $enrollmentId = $this->enroll($client, $context['courseId']);
        $this->recordProgress($client, $enrollmentId, $context['requiredLessonIds'][0], 'completed');

        $token = $this->csrfToken($client, 'complete_enrollment');
        $client->request('POST', '/enrollments/'.$enrollmentId.'/complete', ['_csrf_token' => $token]);
        self::assertTrue($client->getResponse()->isRedirect());

        $events = $this->connection()->fetchAllAssociative(
            'SELECT event_type, aggregate_id, payload FROM outbox_messages ORDER BY created_at, event_type',
        );
        $types = array_map(static function (array $row): string {
            $type = $row['event_type'] ?? null;
            self::assertIsString($type);

            return $type;
        }, $events);

        self::assertContains('EnrollmentCreated', $types);
        self::assertContains('EnrollmentCancelled', $types);
        self::assertContains('EnrollmentCompleted', $types);
        self::assertSame(4, \count($events));

        foreach ($events as $event) {
            $rawPayload = $event['payload'] ?? null;
            self::assertIsString($rawPayload);
            $payload = json_decode($rawPayload, true, 512, \JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            self::assertArrayNotHasKey('password', $payload);
            self::assertArrayNotHasKey('password_hash', $payload);
            self::assertArrayNotHasKey('token', $payload);
            self::assertArrayNotHasKey('email', $payload);
            self::assertArrayHasKey('enrollment_id', $payload);
            self::assertSame($event['aggregate_id'], $payload['enrollment_id']);
        }

        self::assertSame(
            $enrollmentId,
            $this->connection()->fetchOne(
                "SELECT aggregate_id FROM outbox_messages WHERE event_type = 'EnrollmentCompleted'",
            ),
        );
    }

    protected function client(): KernelBrowser
    {
        self::ensureKernelShutdown();

        return parent::client();
    }

    /**
     * @return array{
     *     organisationId: string,
     *     adminId: string,
     *     learnerId: string,
     *     courseId: string,
     *     versionId: string,
     *     requiredModuleId: null|string,
     *     requiredLessonIds: list<string>,
     *     optionalModuleId: null|string,
     *     optionalLessonIds: list<string>
     * }
     */
    private function seedPublishedCourse(
        string $prefix,
        bool $withRequiredModule = true,
        int $requiredLessons = 1,
        bool $withOptionalModule = false,
    ): array {
        $organisationId = $this->createOrganisationDirect($prefix.' organisation', $prefix.'-org');
        $adminId = $this->createVerifiedUser($prefix.'-admin@example.com', 'platform:admin');
        $learnerId = $this->createVerifiedUser($prefix.'-learner@example.com', 'lms:learner');

        $this->addMembershipDirect($organisationId, $adminId);
        $this->addMembershipDirect($organisationId, $learnerId);

        $courseId = $this->createCourseDirect($organisationId, $adminId, $prefix.'-course');
        $content = $this->createPublishedVersion(
            $courseId,
            $adminId,
            1,
            $withRequiredModule,
            $requiredLessons,
            $withOptionalModule,
        );

        return [
            'organisationId' => $organisationId,
            'adminId' => $adminId,
            'learnerId' => $learnerId,
            'courseId' => $courseId,
            'versionId' => $content['versionId'],
            'requiredModuleId' => $content['requiredModuleId'],
            'requiredLessonIds' => $content['requiredLessonIds'],
            'optionalModuleId' => $content['optionalModuleId'],
            'optionalLessonIds' => $content['optionalLessonIds'],
        ];
    }

    /**
     * @return array{
     *     versionId: string,
     *     requiredModuleId: null|string,
     *     requiredLessonIds: list<string>,
     *     optionalModuleId: null|string,
     *     optionalLessonIds: list<string>
     * }
     */
    private function createPublishedVersion(
        string $courseId,
        string $adminId,
        int $versionNumber,
        bool $withRequiredModule,
        int $requiredLessons,
        bool $withOptionalModule,
    ): array {
        $clock = $this->service(ClockInterface::class);
        $versions = $this->service(CourseVersionRepositoryInterface::class);
        $modules = $this->service(CourseModuleRepositoryInterface::class);
        $lessons = $this->service(LessonRepositoryInterface::class);
        $contents = $this->service(LessonContentRepositoryInterface::class);
        $courses = $this->service(CourseRepositoryInterface::class);

        $now = $clock->now();
        $versionId = Uuid::v7()->toRfc4122();

        $version = CourseVersion::createDraft(
            CourseVersionId::fromString($versionId),
            CourseId::fromString($courseId),
            $versionNumber,
            'Version '.$versionNumber,
            'Enrollment flow fixture.',
            UserIdReference::fromString($adminId),
            $now,
        );
        $version->submitForReview();
        $version->publish($now);
        $versions->save($version);

        $requiredModuleId = null;
        $requiredLessonIds = [];
        $optionalModuleId = null;
        $optionalLessonIds = [];

        if ($withRequiredModule) {
            $requiredModuleId = $this->createModule(
                $versionId,
                'Required module',
                0,
                true,
                $requiredLessons,
                $lessons,
                $modules,
                $contents,
            );

            foreach ($lessons->listByModule(CourseModuleId::fromString($requiredModuleId)) as $lesson) {
                $requiredLessonIds[] = $lesson->id()->value();
            }
        }

        if ($withOptionalModule) {
            $optionalModuleId = $this->createModule(
                $versionId,
                'Optional module',
                null === $requiredModuleId ? 0 : 1,
                false,
                1,
                $lessons,
                $modules,
                $contents,
            );

            foreach ($lessons->listByModule(CourseModuleId::fromString($optionalModuleId)) as $lesson) {
                $optionalLessonIds[] = $lesson->id()->value();
            }
        }

        $course = $courses->findById(CourseId::fromString($courseId));
        self::assertNotNull($course);

        $course->publishVersion($versionId, $now);
        $courses->updatePublishedVersion($course);

        return [
            'versionId' => $versionId,
            'requiredModuleId' => $requiredModuleId,
            'requiredLessonIds' => $requiredLessonIds,
            'optionalModuleId' => $optionalModuleId,
            'optionalLessonIds' => $optionalLessonIds,
        ];
    }

    /**
     * @return string the new published version id
     */
    private function publishAdditionalVersion(
        string $courseId,
        string $adminId,
        int $versionNumber,
    ): string {
        $clock = $this->service(ClockInterface::class);
        $versions = $this->service(CourseVersionRepositoryInterface::class);
        $courses = $this->service(CourseRepositoryInterface::class);

        $now = $clock->now();
        $versionId = Uuid::v7()->toRfc4122();

        $version = CourseVersion::createDraft(
            CourseVersionId::fromString($versionId),
            CourseId::fromString($courseId),
            $versionNumber,
            'Version '.$versionNumber,
            'Published update for re-enrollment.',
            UserIdReference::fromString($adminId),
            $now,
        );
        $version->submitForReview();
        $version->publish($now);
        $versions->save($version);

        $course = $courses->findById(CourseId::fromString($courseId));
        self::assertNotNull($course);
        $course->publishVersion($versionId, $now);
        $courses->updatePublishedVersion($course);

        return $versionId;
    }

    private function createModule(
        string $versionId,
        string $title,
        int $position,
        bool $isRequired,
        int $lessonCount,
        LessonRepositoryInterface $lessons,
        CourseModuleRepositoryInterface $modules,
        LessonContentRepositoryInterface $contents,
    ): string {
        $clock = $this->service(ClockInterface::class);
        $now = $clock->now();
        $moduleId = Uuid::v7()->toRfc4122();

        $module = CourseModule::create(
            CourseModuleId::fromString($moduleId),
            CourseVersionId::fromString($versionId),
            $title,
            'Fixture module.',
            $position,
            $isRequired,
            $now,
        );
        $modules->save($module);

        for ($index = 0; $index < $lessonCount; ++$index) {
            $lessonId = Uuid::v7()->toRfc4122();
            $lesson = Lesson::create(
                LessonId::fromString($lessonId),
                CourseModuleId::fromString($moduleId),
                $title.' lesson '.($index + 1),
                $index,
                $now,
            );
            $lessons->save($lesson);

            $contents->save(LessonContent::create(
                Uuid::v7()->toRfc4122(),
                LessonId::fromString($lessonId),
                '<p>Sanitised fixture content.</p>',
                ['format' => 'html'],
                $now,
            ));
        }

        return $moduleId;
    }

    private function createCourseDirect(string $organisationId, string $ownerId, string $slug): string
    {
        $courses = $this->service(CourseRepositoryInterface::class);
        $clock = $this->service(ClockInterface::class);
        $courseId = Uuid::v7()->toRfc4122();

        $course = Course::create(
            CourseId::fromString($courseId),
            OrganisationIdReference::fromString($organisationId),
            UserIdReference::fromString($ownerId),
            $slug,
            CourseVisibility::ORGANISATION_ONLY,
            $clock->now(),
        );
        $courses->save($course);

        return $courseId;
    }

    private function createVerifiedUser(string $email, ?string $roleCode = null): string
    {
        $users = $this->service(UserRepositoryInterface::class);
        $uuid = $this->service(UuidGeneratorInterface::class);
        $clock = $this->service(ClockInterface::class);
        $hasher = $this->service(PasswordHasherInterface::class);

        $now = $clock->now();
        $userId = UserId::fromString($uuid->generate());

        $user = User::register($userId, Email::fromString($email), $hasher->hash(self::PASSWORD), $now);
        $user->verifyEmail($now);
        $users->save($user);

        if (null !== $roleCode) {
            $this->assignRole($userId, $roleCode);
        }

        return $userId->value();
    }

    private function assignRole(UserId $userId, string $roleCode): void
    {
        $roles = $this->service(RoleRepositoryInterface::class);
        $clock = $this->service(ClockInterface::class);

        $roles->assign($userId, RoleIdentifier::fromString($roleCode), null, null, $clock->now());
    }

    private function createOrganisationDirect(string $name, string $slug): string
    {
        $organisations = $this->service(OrganisationRepositoryInterface::class);
        $uuid = $this->service(UuidGeneratorInterface::class);
        $clock = $this->service(ClockInterface::class);

        $organisation = Organisation::create(
            OrganisationId::fromString($uuid->generate()),
            $name,
            $slug,
            $clock->now(),
        );
        $organisations->save($organisation);

        return $organisation->id()->value();
    }

    private function addMembershipDirect(string $organisationId, string $userId): void
    {
        $memberships = $this->service(MembershipRepositoryInterface::class);
        $uuid = $this->service(UuidGeneratorInterface::class);
        $clock = $this->service(ClockInterface::class);

        $memberships->save(Membership::join(
            $uuid->generate(),
            OrganisationId::fromString($organisationId),
            UserIdReference::fromString($userId),
            $clock->now(),
        ));
    }

    private function login(KernelBrowser $client, string $email): void
    {
        $crawler = $client->request('GET', '/login');

        $client->request('POST', '/login', [
            'email' => $email,
            'password' => self::PASSWORD,
            '_csrf_token' => $this->csrfTokenFromCrawler($crawler),
        ]);

        self::assertTrue($client->getResponse()->isRedirect());
        $client->followRedirect();

        if (null !== $client->getResponse()->headers->get('Location')) {
            $client->followRedirect();
        }
    }

    private function enroll(KernelBrowser $client, string $courseId): string
    {
        $token = $this->csrfToken($client, 'enroll_course');
        $client->request('POST', '/courses/'.$courseId.'/enroll', ['_csrf_token' => $token]);
        self::assertTrue($client->getResponse()->isRedirect());

        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#/enrollments/[0-9a-fA-F-]{36}$#', $location);

        return substr($location, strrpos($location, '/') + 1);
    }

    private function recordProgress(KernelBrowser $client, string $enrollmentId, string $lessonId, string $status): void
    {
        $token = $this->csrfToken($client, 'lesson_progress');
        $client->request('POST', '/enrollments/'.$enrollmentId.'/lessons/'.$lessonId.'/progress', [
            'status' => $status,
            '_csrf_token' => $token,
        ]);
        self::assertTrue($client->getResponse()->isRedirect());
    }

    private function csrfToken(KernelBrowser $client, string $tokenId): string
    {
        $client->request('GET', '/enrollments');
        $session = $client->getRequest()->getSession();
        $token = bin2hex(random_bytes(16));
        $session->set('_csrf/'.$tokenId, $token);
        $session->save();

        return $token;
    }

    private function csrfTokenFromCrawler(Crawler $crawler): string
    {
        $nodes = $crawler->filter('input[name="_csrf_token"]');
        self::assertGreaterThan(0, $nodes->count(), 'CSRF field "_csrf_token" is missing.');

        $value = $nodes->first()->attr('value');
        self::assertNotNull($value);

        return $value;
    }

    /**
     * @return null|array<string, mixed>
     */
    private function activeEnrollment(string $learnerId, string $courseId): ?array
    {
        $row = $this->connection()->fetchAssociative(
            "SELECT * FROM enrollments WHERE learner_user_id = :learner AND course_id = :course AND status = 'active'",
            ['learner' => $learnerId, 'course' => $courseId],
        );

        return false === $row ? null : $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function enrollmentRow(string $enrollmentId): array
    {
        $row = $this->connection()->fetchAssociative(
            'SELECT * FROM enrollments WHERE id = :id',
            ['id' => $enrollmentId],
        );
        self::assertIsArray($row);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function lessonProgressRow(string $enrollmentId, string $lessonId): array
    {
        $row = $this->connection()->fetchAssociative(
            'SELECT * FROM lesson_progress WHERE enrollment_id = :enrollment AND lesson_id = :lesson',
            ['enrollment' => $enrollmentId, 'lesson' => $lessonId],
        );
        self::assertIsArray($row);

        return $row;
    }

    /**
     * @param array{
     *     organisationId: string,
     *     adminId: string,
     *     learnerId: string,
     *     courseId: string,
     *     versionId: string,
     *     requiredModuleId: null|string,
     *     requiredLessonIds: list<string>,
     *     optionalModuleId: null|string,
     *     optionalLessonIds: list<string>
     * } $context
     */
    private function requiredModuleId(array $context): string
    {
        self::assertNotNull($context['requiredModuleId']);

        return $context['requiredModuleId'];
    }

    private function moduleProgressStatus(string $enrollmentId, string $moduleId): string
    {
        $value = $this->connection()->fetchOne(
            'SELECT status FROM module_progress WHERE enrollment_id = :enrollment AND course_module_id = :module',
            ['enrollment' => $enrollmentId, 'module' => $moduleId],
        );
        self::assertIsString($value);

        return $value;
    }

    private function countRows(string $table): int
    {
        $count = $this->connection()->fetchOne('SELECT COUNT(*) FROM '.$table);
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private function countRowsForLearner(string $table, string $learnerId): int
    {
        $count = $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM '.$table.' WHERE learner_user_id = :learner',
            ['learner' => $learnerId],
        );
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private function countRowsForLearnerAndStatus(string $learnerId, string $status): int
    {
        $count = $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM enrollments WHERE learner_user_id = :learner AND status = :status',
            ['learner' => $learnerId, 'status' => $status],
        );
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private function content(KernelBrowser $client): string
    {
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);

        return $content;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }
}
