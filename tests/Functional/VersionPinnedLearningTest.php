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
 * Regression for P1-04: an enrollment is pinned to the course version that was
 * published when the learner enrolled. Publishing a newer version must never
 * swap content under an existing enrollment.
 *
 * @internal
 *
 * @coversNothing
 */
final class VersionPinnedLearningTest extends DatabaseWebTestCase
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

    public function testExistingEnrollmentStaysPinnedToOriginalVersionAfterNewPublish(): void
    {
        $organisationId = $this->createOrganisationDirect('Pinned Organisation', 'pinned-org');
        $adminId = $this->createVerifiedUser('pinned-admin@example.com', 'platform:admin');
        $learnerId = $this->createVerifiedUser('pinned-learner@example.com', 'lms:learner');
        $newLearnerId = $this->createVerifiedUser('pinned-new-learner@example.com', 'lms:learner');

        $this->addMembershipDirect($organisationId, $adminId);
        $this->addMembershipDirect($organisationId, $learnerId);
        $this->addMembershipDirect($organisationId, $newLearnerId);

        $courseId = $this->createCourseDirect($organisationId, $adminId, 'pinned-course');

        $first = $this->createPublishedVersion($courseId, $adminId, 1, 'Version one title', [
            [
                'title' => 'Version one module',
                'required' => true,
                'lessons' => [
                    ['title' => 'Version one lesson A', 'html' => '<script>alert("v1")</script><p>Version one content marker.</p>'],
                    ['title' => 'Version one lesson B', 'html' => '<p>Version one second marker.</p>'],
                ],
            ],
        ]);

        $client = $this->client();
        $this->login($client, 'pinned-learner@example.com');

        $enrollmentId = $this->enroll($client, $courseId);
        $enrollment = $this->enrollmentRow($enrollmentId);
        self::assertSame($first['versionId'], $enrollment['course_version_id']);

        $lessonA = $first['modules'][0]['lessonIds'][0];
        $lessonB = $first['modules'][0]['lessonIds'][1];
        $this->recordProgress($client, $enrollmentId, $lessonA, 'completed');

        $second = $this->createPublishedVersion($courseId, $adminId, 2, 'Version two title', [
            [
                'title' => 'Version two module',
                'required' => true,
                'lessons' => [
                    ['title' => 'Version two lesson', 'html' => '<p>Version two content marker.</p>'],
                ],
            ],
        ]);
        self::assertNotSame($first['versionId'], $second['versionId']);

        $client->request('GET', '/enrollments/'.$enrollmentId);
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $pinned = $this->content($client);

        self::assertStringContainsString('Version one title', $pinned);
        self::assertStringContainsString('Version one lesson A', $pinned);
        self::assertStringContainsString('Version one lesson B', $pinned);
        self::assertStringContainsString('Version one content marker.', $pinned);
        self::assertStringContainsString('Version one second marker.', $pinned);
        self::assertStringNotContainsString('Version two content marker.', $pinned);
        self::assertStringNotContainsString('Version two lesson', $pinned);
        self::assertStringNotContainsString('<script', $pinned);
        self::assertStringNotContainsString('alert("v1")', $pinned);
        self::assertStringContainsString('completed', $pinned);

        $this->recordProgress($client, $enrollmentId, $lessonB, 'completed');
        self::assertSame(
            2,
            $this->countRowsForEnrollment('lesson_progress', $enrollmentId),
        );
        self::assertSame($first['versionId'], $this->enrollmentRow($enrollmentId)['course_version_id']);

        $client->request('GET', '/enrollments');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $index = $this->content($client);
        self::assertStringContainsString('Version one title', $index);
        self::assertStringNotContainsString('Version two title', $index);

        $foreignOrganisationId = $this->createOrganisationDirect('Pinned Foreign', 'pinned-foreign-org');
        $outsiderId = $this->createVerifiedUser('pinned-outsider@example.com', 'lms:learner');
        $this->addMembershipDirect($foreignOrganisationId, $outsiderId);

        $outsiderClient = $this->client();
        $this->login($outsiderClient, 'pinned-outsider@example.com');
        $outsiderClient->request('GET', '/enrollments/'.$enrollmentId);
        self::assertSame(403, $outsiderClient->getResponse()->getStatusCode());

        $newClient = $this->client();
        $this->login($newClient, 'pinned-new-learner@example.com');
        $newEnrollmentId = $this->enroll($newClient, $courseId);
        $newEnrollment = $this->enrollmentRow($newEnrollmentId);
        self::assertSame($second['versionId'], $newEnrollment['course_version_id']);
        self::assertSame($learnerId, $this->enrollmentRow($enrollmentId)['learner_user_id']);

        $newClient->request('GET', '/enrollments/'.$newEnrollmentId);
        self::assertSame(200, $newClient->getResponse()->getStatusCode());
        $fresh = $this->content($newClient);
        self::assertStringContainsString('Version two title', $fresh);
        self::assertStringContainsString('Version two content marker.', $fresh);
        self::assertStringNotContainsString('Version one content marker.', $fresh);
    }

    public function testArchivedVersionContentRemainsAccessibleForExistingEnrollment(): void
    {
        $organisationId = $this->createOrganisationDirect('Archived Organisation', 'archived-org');
        $adminId = $this->createVerifiedUser('archived-admin@example.com', 'platform:admin');
        $learnerId = $this->createVerifiedUser('archived-learner@example.com', 'lms:learner');
        $this->addMembershipDirect($organisationId, $adminId);
        $this->addMembershipDirect($organisationId, $learnerId);

        $courseId = $this->createCourseDirect($organisationId, $adminId, 'archived-course');
        $version = $this->createPublishedVersion($courseId, $adminId, 1, 'Archived version title', [
            [
                'title' => 'Archived module',
                'required' => true,
                'lessons' => [
                    ['title' => 'Archived lesson', 'html' => '<p>Archived content marker.</p>'],
                ],
            ],
        ]);

        $client = $this->client();
        $this->login($client, 'archived-learner@example.com');

        $enrollmentId = $this->enroll($client, $courseId);
        $this->recordProgress($client, $enrollmentId, $version['modules'][0]['lessonIds'][0], 'completed');

        $this->archiveVersion($courseId, $version['versionId']);

        $client->request('GET', '/enrollments/'.$enrollmentId);
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $content = $this->content($client);

        self::assertStringContainsString('Archived version title', $content);
        self::assertStringContainsString('Archived content marker.', $content);
        self::assertStringContainsString('completed', $content);
        self::assertSame($version['versionId'], $this->enrollmentRow($enrollmentId)['course_version_id']);
    }

    protected function client(): KernelBrowser
    {
        self::ensureKernelShutdown();

        return parent::client();
    }

    /**
     * @param list<array{
     *     title: string,
     *     required: bool,
     *     lessons: list<array{title: string, html: string}>
     * }> $moduleSpecs
     *
     * @return array{
     *     versionId: string,
     *     modules: list<array{id: string, lessonIds: list<string>}>
     * }
     */
    private function createPublishedVersion(
        string $courseId,
        string $adminId,
        int $versionNumber,
        string $title,
        array $moduleSpecs,
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
            'Version '.$versionNumber.' description.',
            UserIdReference::fromString($adminId),
            $now,
        );
        $version->submitForReview();
        $version->publish($now);
        $versions->save($version);

        $moduleRows = [];
        $modulePosition = 0;

        foreach ($moduleSpecs as $moduleSpec) {
            $moduleId = Uuid::v7()->toRfc4122();
            $modules->save(CourseModule::create(
                CourseModuleId::fromString($moduleId),
                CourseVersionId::fromString($versionId),
                $moduleSpec['title'],
                'Fixture module.',
                $modulePosition,
                $moduleSpec['required'],
                $now,
            ));
            ++$modulePosition;

            $lessonIds = [];
            $lessonPosition = 0;

            foreach ($moduleSpec['lessons'] as $lessonSpec) {
                $lessonId = Uuid::v7()->toRfc4122();
                $lessons->save(Lesson::create(
                    LessonId::fromString($lessonId),
                    CourseModuleId::fromString($moduleId),
                    $lessonSpec['title'],
                    $lessonPosition,
                    $now,
                ));
                ++$lessonPosition;

                $contents->save(LessonContent::create(
                    Uuid::v7()->toRfc4122(),
                    LessonId::fromString($lessonId),
                    $lessonSpec['html'],
                    ['format' => 'html'],
                    $now,
                ));

                $lessonIds[] = $lessonId;
            }

            $moduleRows[] = ['id' => $moduleId, 'lessonIds' => $lessonIds];
        }

        $course = $courses->findById(CourseId::fromString($courseId));
        self::assertNotNull($course);
        $course->publishVersion($versionId, $now);
        $courses->updatePublishedVersion($course);

        return ['versionId' => $versionId, 'modules' => $moduleRows];
    }

    private function archiveVersion(string $courseId, string $versionId): void
    {
        $clock = $this->service(ClockInterface::class);
        $versions = $this->service(CourseVersionRepositoryInterface::class);
        $courses = $this->service(CourseRepositoryInterface::class);

        $version = $versions->findById(CourseVersionId::fromString($versionId));
        self::assertNotNull($version);
        $version->archive();
        $versions->save($version);

        $course = $courses->findById(CourseId::fromString($courseId));
        self::assertNotNull($course);
        $course->clearPublishedVersion($clock->now());
        $courses->clearPublishedVersion($course);
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

    private function countRowsForEnrollment(string $table, string $enrollmentId): int
    {
        $count = $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM '.$table.' WHERE enrollment_id = :enrollment',
            ['enrollment' => $enrollmentId],
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
