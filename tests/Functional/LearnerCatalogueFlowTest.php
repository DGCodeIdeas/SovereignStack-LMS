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
use SovereignStack\Lms\Organisation\Domain\Entity\Membership;
use SovereignStack\Lms\Organisation\Domain\Entity\Organisation;
use SovereignStack\Lms\Organisation\Domain\Repository\MembershipRepositoryInterface;
use SovereignStack\Lms\Organisation\Domain\Repository\OrganisationRepositoryInterface;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use SovereignStack\Lms\Tests\Functional\Fixtures\DeterministicTokenGenerator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class LearnerCatalogueFlowTest extends DatabaseWebTestCase
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

    public function testMemberLearnerSeesOrganisationCourseAndCanOpenAndEnroll(): void
    {
        $context = $this->seedPublishedCourse('member');
        $client = $this->client();
        $this->login($client, 'member-learner@example.com');

        $client->request('GET', '/learn/courses');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $catalogue = $this->content($client);
        self::assertStringContainsString('Member course', $catalogue);
        self::assertStringContainsString('Member description', $catalogue);
        self::assertStringContainsString('1', $catalogue);

        $crawler = $client->request('GET', '/learn/courses/'.$context['courseId']);
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $detail = $this->content($client);
        self::assertStringContainsString('Member course', $detail);
        self::assertStringContainsString('Member description', $detail);
        self::assertStringContainsString('Member module', $detail);
        self::assertStringContainsString('Member lesson', $detail);
        self::assertSame(
            1,
            $crawler->filter('form[action="/courses/'.$context['courseId'].'/enroll"] input[name="_csrf_token"]')->count(),
        );

        $enrollmentId = $this->enroll($client, $context['courseId']);
        $enrollment = $this->enrollmentRow($enrollmentId);
        self::assertSame('active', $enrollment['status']);
        self::assertSame($context['learnerId'], $enrollment['learner_user_id']);
    }

    public function testOutsiderCannotSeeOrEnrollInOrganisationOnlyCourse(): void
    {
        $context = $this->seedPublishedCourse('outsider');
        $otherOrganisationId = $this->createOrganisationDirect('Outsider Organisation', 'outsider-other-org');
        $outsiderId = $this->createVerifiedUser('outsider-visitor@example.com', 'lms:learner');
        $this->addMembershipDirect($otherOrganisationId, $outsiderId);

        $client = $this->client();
        $this->login($client, 'outsider-visitor@example.com');

        $client->request('GET', '/learn/courses');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringNotContainsString('Outsider course', $this->content($client));

        $client->request('GET', '/learn/courses/'.$context['courseId']);
        self::assertSame(404, $client->getResponse()->getStatusCode());

        $token = $this->csrfToken($client, 'enroll_course');
        $client->request('POST', '/courses/'.$context['courseId'].'/enroll', ['_csrf_token' => $token]);
        self::assertSame(403, $client->getResponse()->getStatusCode());
        self::assertSame(0, $this->countRows('enrollments'));
    }

    public function testPublicCourseIsVisibleAndEnrollableFromAnotherOrganisation(): void
    {
        $context = $this->seedPublishedCourse('public', 'public');
        $otherOrganisationId = $this->createOrganisationDirect('Public Other', 'public-other-org');
        $otherLearnerId = $this->createVerifiedUser('public-other-learner@example.com', 'lms:learner');
        $this->addMembershipDirect($otherOrganisationId, $otherLearnerId);

        $client = $this->client();
        $this->login($client, 'public-other-learner@example.com');

        $client->request('GET', '/learn/courses');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('Public course', $this->content($client));

        $client->request('GET', '/learn/courses/'.$context['courseId']);
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $enrollmentId = $this->enroll($client, $context['courseId']);
        $enrollment = $this->enrollmentRow($enrollmentId);
        self::assertSame($otherLearnerId, $enrollment['learner_user_id']);
        self::assertSame($context['organisationId'], $enrollment['organisation_id']);
    }

    public function testRestrictedCourseIsHiddenAndEnrollmentIsDeniedForLearners(): void
    {
        $context = $this->seedPublishedCourse('restricted', 'restricted');
        $client = $this->client();
        $this->login($client, 'restricted-learner@example.com');

        $client->request('GET', '/learn/courses');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringNotContainsString('Restricted course', $this->content($client));

        $client->request('GET', '/learn/courses/'.$context['courseId']);
        self::assertSame(404, $client->getResponse()->getStatusCode());

        $token = $this->csrfToken($client, 'enroll_course');
        $client->request('POST', '/courses/'.$context['courseId'].'/enroll', ['_csrf_token' => $token]);
        self::assertSame(403, $client->getResponse()->getStatusCode());
        self::assertSame(0, $this->countRows('enrollments'));
    }

    public function testDraftReviewAndArchivedCoursesAreNotVisible(): void
    {
        $organisationId = $this->createOrganisationDirect('Unpublished Organisation', 'unpublished-org');
        $adminId = $this->createVerifiedUser('unpublished-admin@example.com', 'platform:admin');
        $learnerId = $this->createVerifiedUser('unpublished-learner@example.com', 'lms:learner');
        $this->addMembershipDirect($organisationId, $adminId);
        $this->addMembershipDirect($organisationId, $learnerId);

        $draftCourseId = $this->createCourseDirect($organisationId, $adminId, 'draft-course', 'organisation_only');
        $this->createUnpublishedVersion($draftCourseId, $adminId, 1, 'draft', 'Draft course');

        $reviewCourseId = $this->createCourseDirect($organisationId, $adminId, 'review-course', 'organisation_only');
        $this->createUnpublishedVersion($reviewCourseId, $adminId, 1, 'review', 'Review course');

        $archivedCourseId = $this->createCourseDirect($organisationId, $adminId, 'archived-course', 'archived');
        $this->createPublishedVersion($archivedCourseId, $adminId, 1, 'Archived course', 'Archived description');

        $client = $this->client();
        $this->login($client, 'unpublished-learner@example.com');

        $client->request('GET', '/learn/courses');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $catalogue = $this->content($client);
        self::assertStringNotContainsString('Draft course', $catalogue);
        self::assertStringNotContainsString('Review course', $catalogue);
        self::assertStringNotContainsString('Archived course', $catalogue);

        foreach ([$draftCourseId, $reviewCourseId, $archivedCourseId] as $courseId) {
            $client->request('GET', '/learn/courses/'.$courseId);
            self::assertSame(404, $client->getResponse()->getStatusCode());
        }
    }

    public function testDuplicateActiveEnrollmentShowsErrorAndKeepsSingleRow(): void
    {
        $context = $this->seedPublishedCourse('duplicate');
        $client = $this->client();
        $this->login($client, 'duplicate-learner@example.com');

        $this->enroll($client, $context['courseId']);

        $token = $this->csrfToken($client, 'enroll_course');
        $client->request('POST', '/courses/'.$context['courseId'].'/enroll', ['_csrf_token' => $token]);
        self::assertTrue($client->getResponse()->isRedirect());
        self::assertStringContainsString(
            '/learn/courses/'.$context['courseId'],
            (string) $client->getResponse()->headers->get('Location'),
        );

        $client->followRedirect();
        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('already has an active enrollment', $this->content($client));
        self::assertSame(1, $this->countRows('enrollments'));
        self::assertSame(1, $this->countRowsForLearner('enrollments', $context['learnerId']));
    }

    public function testEnrollmentRequiresCsrfToken(): void
    {
        $context = $this->seedPublishedCourse('csrf');
        $client = $this->client();
        $this->login($client, 'csrf-learner@example.com');

        $client->request('POST', '/courses/'.$context['courseId'].'/enroll');
        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertSame(0, $this->countRows('enrollments'));
    }

    public function testEnrollRedirectsToDetailAndReenrollKeepsHistory(): void
    {
        $context = $this->seedPublishedCourse('reenroll');
        $client = $this->client();
        $this->login($client, 'reenroll-learner@example.com');

        $firstId = $this->enroll($client, $context['courseId']);

        $token = $this->csrfToken($client, 'cancel_enrollment');
        $client->request('POST', '/enrollments/'.$firstId.'/cancel', ['_csrf_token' => $token]);
        self::assertTrue($client->getResponse()->isRedirect());
        self::assertSame('cancelled', $this->enrollmentRow($firstId)['status']);

        $secondVersionId = $this->publishAdditionalVersion($context['courseId'], $context['adminId'], 2);
        self::assertNotSame($context['versionId'], $secondVersionId);

        $secondId = $this->enroll($client, $context['courseId']);
        self::assertNotSame($firstId, $secondId);
        self::assertSame(2, $this->countRows('enrollments'));
        self::assertSame('cancelled', $this->enrollmentRow($firstId)['status']);
        self::assertSame('active', $this->enrollmentRow($secondId)['status']);
        self::assertSame(1, $this->countRowsForLearnerAndStatus($context['learnerId'], 'active'));
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
     *     moduleId: string,
     *     lessonId: string
     * }
     */
    private function seedPublishedCourse(
        string $prefix,
        string $visibility = 'organisation_only',
        bool $learnerMembership = true,
    ): array {
        $organisationId = $this->createOrganisationDirect(ucfirst($prefix).' Organisation', $prefix.'-org');
        $adminId = $this->createVerifiedUser($prefix.'-admin@example.com', 'platform:admin');
        $learnerId = $this->createVerifiedUser($prefix.'-learner@example.com', 'lms:learner');

        $this->addMembershipDirect($organisationId, $adminId);

        if ($learnerMembership) {
            $this->addMembershipDirect($organisationId, $learnerId);
        }

        $courseId = $this->createCourseDirect(
            $organisationId,
            $adminId,
            $prefix.'-course',
            $visibility,
        );
        $content = $this->createPublishedVersion(
            $courseId,
            $adminId,
            1,
            ucfirst($prefix).' course',
            ucfirst($prefix).' description',
            ucfirst($prefix).' module',
            ucfirst($prefix).' lesson',
        );

        return [
            'organisationId' => $organisationId,
            'adminId' => $adminId,
            'learnerId' => $learnerId,
            'courseId' => $courseId,
            'versionId' => $content['versionId'],
            'moduleId' => $content['moduleId'],
            'lessonId' => $content['lessonId'],
        ];
    }

    /**
     * @return array{versionId: string, moduleId: string, lessonId: string}
     */
    private function createPublishedVersion(
        string $courseId,
        string $adminId,
        int $versionNumber,
        string $title,
        string $description,
        string $moduleTitle = 'Required module',
        string $lessonTitle = 'First lesson',
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
            $title,
            $description,
            UserIdReference::fromString($adminId),
            $now,
        );
        $version->submitForReview();
        $version->publish($now);
        $versions->save($version);

        $moduleId = Uuid::v7()->toRfc4122();
        $modules->save(CourseModule::create(
            CourseModuleId::fromString($moduleId),
            CourseVersionId::fromString($versionId),
            $moduleTitle,
            'Fixture module.',
            0,
            true,
            $now,
        ));

        $lessonId = Uuid::v7()->toRfc4122();
        $lessons->save(Lesson::create(
            LessonId::fromString($lessonId),
            CourseModuleId::fromString($moduleId),
            $lessonTitle,
            0,
            $now,
        ));
        $contents->save(LessonContent::create(
            Uuid::v7()->toRfc4122(),
            LessonId::fromString($lessonId),
            '<p>Fixture lesson content.</p>',
            ['format' => 'html'],
            $now,
        ));

        $course = $courses->findById(CourseId::fromString($courseId));
        self::assertNotNull($course);
        $course->publishVersion($versionId, $now);
        $courses->updatePublishedVersion($course);

        return [
            'versionId' => $versionId,
            'moduleId' => $moduleId,
            'lessonId' => $lessonId,
        ];
    }

    private function createUnpublishedVersion(
        string $courseId,
        string $adminId,
        int $versionNumber,
        string $status,
        string $title,
    ): string {
        $clock = $this->service(ClockInterface::class);
        $versions = $this->service(CourseVersionRepositoryInterface::class);

        $version = CourseVersion::createDraft(
            CourseVersionId::fromString(Uuid::v7()->toRfc4122()),
            CourseId::fromString($courseId),
            $versionNumber,
            $title,
            'Unpublished fixture.',
            UserIdReference::fromString($adminId),
            $clock->now(),
        );

        if ('review' === $status) {
            $version->submitForReview();
        }

        $versions->save($version);

        return $version->id()->value();
    }

    private function publishAdditionalVersion(string $courseId, string $adminId, int $versionNumber): string
    {
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

    private function createCourseDirect(string $organisationId, string $ownerId, string $slug, string $visibility): string
    {
        $courses = $this->service(CourseRepositoryInterface::class);
        $clock = $this->service(ClockInterface::class);
        $courseId = Uuid::v7()->toRfc4122();

        $course = Course::create(
            CourseId::fromString($courseId),
            OrganisationIdReference::fromString($organisationId),
            UserIdReference::fromString($ownerId),
            $slug,
            CourseVisibility::from($visibility),
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
            $roles = $this->service(RoleRepositoryInterface::class);
            $roles->assign($userId, RoleIdentifier::fromString($roleCode), null, null, $clock->now());
        }

        return $userId->value();
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
