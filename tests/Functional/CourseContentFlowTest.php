<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Functional;

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
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use SovereignStack\Lms\Tests\Functional\Fixtures\DeterministicTokenGenerator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @internal
 *
 * @coversNothing
 */
final class CourseContentFlowTest extends DatabaseWebTestCase
{
    private const PASSWORD = 'CorrectHorseBatteryStaple1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncate(
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

    public function testPlatformAdminCreatesDraftCourse(): void
    {
        $client = $this->client();
        $adminId = $this->createVerifiedUser('course-admin@example.com', 'platform:admin');
        $organisationId = $this->createOrganisationDirect('Acme Learning', 'acme-learning');
        $this->addMembershipDirect($organisationId, $adminId);
        $this->login($client, 'course-admin@example.com');

        $crawler = $client->request('GET', '/courses/new');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('Acme Learning', $this->content($client));

        $client->request('POST', '/courses', [
            'organisation_id' => $organisationId,
            'title' => 'Security Awareness',
            'description' => 'Annual security training.',
            'slug' => 'security-awareness',
            '_csrf_token' => $this->csrfFromForm($crawler, '/courses'),
        ]);

        self::assertTrue($client->getResponse()->isRedirect());

        $course = $this->courseBySlug('security-awareness');
        self::assertNotNull($course);

        $version = $this->latestVersion($this->asString($course['id'] ?? null));
        self::assertNotNull($version);
        self::assertSame('draft', $version['status']);
        self::assertSame(1, $this->asInt($version['version_number']));

        $client->request('GET', '/courses/'.$course['id']);
        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('Security Awareness', $this->content($client));

        $client->request('GET', '/courses');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('Security Awareness', $this->content($client));
        self::assertStringContainsString('security-awareness', $this->content($client));
    }

    public function testModulesAndLessonsCanBeAddedAndReordered(): void
    {
        $client = $this->client();
        $adminId = $this->createVerifiedUser('admin@example.com', 'platform:admin');
        $organisationId = $this->createOrganisationDirect('Acme', 'acme');
        $this->addMembershipDirect($organisationId, $adminId);
        $this->login($client, 'admin@example.com');

        $courseId = $this->createCourseViaHttp($client, $organisationId, 'Ordering Course', 'ordering-course');

        $firstModule = $this->addModuleViaHttp($client, $courseId, 'Module A');
        $this->addModuleViaHttp($client, $courseId, 'Module B');
        $thirdModule = $this->addModuleViaHttp($client, $courseId, 'Module C');

        $firstLesson = $this->addLessonViaHttp($client, $courseId, $firstModule, 'Lesson One');
        $secondLesson = $this->addLessonViaHttp($client, $courseId, $firstModule, 'Lesson Two');

        $moduleIds = $this->moduleIds($courseId);
        self::assertCount(3, $moduleIds);

        $crawler = $client->request('GET', '/courses/'.$courseId);
        $client->request('POST', '/courses/'.$courseId.'/modules/reorder', [
            'module_ids' => array_reverse($moduleIds),
            '_csrf_token' => $this->csrfFromForm($crawler, '/courses/'.$courseId.'/modules/reorder'),
        ]);
        self::assertTrue($client->getResponse()->isRedirect());

        self::assertSame(
            ['Module C', 'Module B', 'Module A'],
            $this->moduleTitles($courseId),
        );
        self::assertSame([0, 1, 2], $this->modulePositions($courseId));

        $crawler = $client->request('GET', '/courses/'.$courseId);
        $order = $this->headingOrder($this->content($client), 'h3');
        self::assertSame(['Module C', 'Module B', 'Module A'], $order);

        $client->request('POST', '/courses/'.$courseId.'/modules/'.$firstModule.'/lessons/reorder', [
            'lesson_ids' => [$secondLesson, $firstLesson],
            '_csrf_token' => $this->csrfFromForm(
                $crawler,
                '/courses/'.$courseId.'/modules/'.$firstModule.'/lessons/reorder',
            ),
        ]);
        self::assertTrue($client->getResponse()->isRedirect());

        self::assertSame([0, 1], $this->lessonPositions($firstModule));
        self::assertSame(['Lesson Two', 'Lesson One'], $this->lessonTitles($firstModule));
        self::assertSame(2, $this->countRows('lessons'));

        self::assertNotNull($thirdModule);
        self::assertSame(3, $this->countRows('course_modules'));
    }

    public function testLessonContentIsSanitisedOnSave(): void
    {
        $client = $this->client();
        $adminId = $this->createVerifiedUser('admin@example.com', 'platform:admin');
        $organisationId = $this->createOrganisationDirect('Acme', 'acme');
        $this->addMembershipDirect($organisationId, $adminId);
        $this->login($client, 'admin@example.com');

        $courseId = $this->createCourseViaHttp($client, $organisationId, 'Content Course', 'content-course');
        $moduleId = $this->addModuleViaHttp($client, $courseId, 'Module One');
        $lessonId = $this->addLessonViaHttp($client, $courseId, $moduleId, 'Lesson One');

        $crawler = $client->request('GET', '/courses/'.$courseId);
        $client->request('POST', '/courses/'.$courseId.'/lessons/'.$lessonId.'/content', [
            'content_html' => '<script>alert(1)</script><p>Allowed body</p>',
            'metadata' => '{"format":"html"}',
            '_csrf_token' => $this->csrfFromForm(
                $crawler,
                '/courses/'.$courseId.'/lessons/'.$lessonId.'/content',
            ),
        ]);

        self::assertTrue($client->getResponse()->isRedirect());

        $content = $this->connection()->fetchAssociative(
            'SELECT content_html, content_metadata FROM lesson_contents WHERE lesson_id = :lesson_id',
            ['lesson_id' => $lessonId],
        );
        self::assertIsArray($content);

        $html = $this->asString($content['content_html'] ?? null);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringContainsString('<p>Allowed body</p>', $html);
        $metadata = json_decode($this->asString($content['content_metadata'] ?? null), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['format' => 'html'], $metadata);
    }

    public function testReviewPublishAndArchiveLifecycle(): void
    {
        $client = $this->client();
        $adminId = $this->createVerifiedUser('admin@example.com', 'platform:admin');
        $organisationId = $this->createOrganisationDirect('Acme', 'acme');
        $this->addMembershipDirect($organisationId, $adminId);
        $this->login($client, 'admin@example.com');

        $courseId = $this->createCourseViaHttp($client, $organisationId, 'Lifecycle Course', 'lifecycle-course');

        $this->postLifecycle($client, $courseId, 'submit');
        self::assertTrue($client->getResponse()->isRedirect());
        self::assertSame('draft', $this->versionStatus($courseId));

        $moduleId = $this->addModuleViaHttp($client, $courseId, 'Required Module');

        $this->postLifecycle($client, $courseId, 'submit');
        self::assertSame('review', $this->versionStatus($courseId));

        $this->postLifecycle($client, $courseId, 'publish');
        self::assertTrue($client->getResponse()->isRedirect());
        self::assertSame('review', $this->versionStatus($courseId));
        self::assertNull($this->courseBySlug('lifecycle-course')['current_published_version_id'] ?? null);

        $this->postLifecycle($client, $courseId, 'reject');
        self::assertSame('draft', $this->versionStatus($courseId));

        $lessonId = $this->addLessonViaHttp($client, $courseId, $moduleId, 'Published Lesson');
        $crawler = $client->request('GET', '/courses/'.$courseId);
        $client->request('POST', '/courses/'.$courseId.'/lessons/'.$lessonId.'/content', [
            'content_html' => '<p>Ready to publish.</p>',
            '_csrf_token' => $this->csrfFromForm(
                $crawler,
                '/courses/'.$courseId.'/lessons/'.$lessonId.'/content',
            ),
        ]);
        self::assertTrue($client->getResponse()->isRedirect());

        $this->postLifecycle($client, $courseId, 'submit');
        self::assertSame('review', $this->versionStatus($courseId));

        $this->postLifecycle($client, $courseId, 'publish');
        self::assertTrue($client->getResponse()->isRedirect());

        $version = $this->latestVersion($courseId);
        self::assertNotNull($version);
        self::assertSame('published', $version['status']);

        $course = $this->courseBySlug('lifecycle-course');
        self::assertNotNull($course);
        self::assertSame($version['id'], $course['current_published_version_id']);
        self::assertSame($courseId, $course['current_published_version_course_id']);

        $this->postLifecycle($client, $courseId, 'archive');
        self::assertTrue($client->getResponse()->isRedirect());

        $archived = $this->courseBySlug('lifecycle-course');
        self::assertNotNull($archived);
        self::assertNull($archived['current_published_version_id']);
        self::assertNull($archived['current_published_version_course_id']);
        self::assertSame('archived', $this->versionStatus($courseId));
    }

    public function testPublishedVersionRejectsFurtherMutations(): void
    {
        $client = $this->client();
        [$courseId, $moduleId, $lessonId] = $this->publishedCourse($client, 'immutable-course', 'Immutable Course');

        $version = $this->latestVersion($courseId);
        self::assertNotNull($version);

        $originalTitle = $this->asString($version['title']);
        $originalHtml = $this->lessonHtml($lessonId);
        $originalModules = $this->countRows('course_modules');

        $this->postLifecycle($client, $courseId, '', [
            'title' => 'Hacked title',
            'description' => 'Hacked description',
            'slug' => 'immutable-course',
            'visibility' => 'public',
        ]);
        self::assertContains($client->getResponse()->getStatusCode(), [302, 403, 409]);

        $updated = $this->latestVersion($courseId);
        self::assertNotNull($updated);
        self::assertSame($originalTitle, $this->asString($updated['title']));
        self::assertSame('published', $updated['status']);

        $crawler = $client->request('GET', '/courses/'.$courseId);
        $client->request('POST', '/courses/'.$courseId.'/lessons/'.$lessonId.'/content', [
            'content_html' => '<p>Hacked content.</p>',
            '_csrf_token' => $this->csrfFromForm(
                $crawler,
                '/courses/'.$courseId.'/lessons/'.$lessonId.'/content',
            ),
        ]);
        self::assertContains($client->getResponse()->getStatusCode(), [302, 403, 409]);
        self::assertSame($originalHtml, $this->lessonHtml($lessonId));

        $this->postLifecycle($client, $courseId, 'modules', [
            'title' => 'Hacked module',
            'description' => '',
            'is_required' => '1',
        ]);
        self::assertContains($client->getResponse()->getStatusCode(), [302, 403, 409]);
        self::assertSame($originalModules, $this->countRows('course_modules'));

        $this->postLifecycle($client, $courseId, 'modules/'.$moduleId.'/delete');
        self::assertContains($client->getResponse()->getStatusCode(), [302, 403, 409]);
        self::assertSame($originalModules, $this->countRows('course_modules'));
    }

    public function testCrossOrganisationCourseAccessIsDenied(): void
    {
        $this->createVerifiedUser('admin@example.com', 'platform:admin');
        $instructorId = $this->createVerifiedUser('instructor@example.com');

        $orgA = $this->createOrganisationDirect('Org A', 'org-a');
        $orgB = $this->createOrganisationDirect('Org B', 'org-b');

        $this->addMembershipDirect($orgA, $instructorId);
        $this->assignOrganisationRole($instructorId, 'lms:instructor', $orgA);

        $adminClient = $this->client();
        $this->login($adminClient, 'admin@example.com');
        $adminCourseId = $this->createCourseViaHttp($adminClient, $orgB, 'Org B Course', 'org-b-course');

        $client = $this->client();
        $this->login($client, 'instructor@example.com');

        $ownCourseId = $this->createCourseViaHttp($client, $orgA, 'Org A Course', 'org-a-course');

        $client->request('GET', '/courses');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $content = $this->content($client);
        self::assertStringContainsString('Org A Course', $content);
        self::assertStringNotContainsString('Org B Course', $content);
        self::assertStringNotContainsString('org-b-course', $content);

        $crawler = $client->request('GET', '/courses/new');
        $client->request('POST', '/courses', [
            'organisation_id' => $orgB,
            'title' => 'Forbidden Course',
            'description' => 'Should not exist.',
            'slug' => 'forbidden-course',
            '_csrf_token' => $this->csrfFromForm($crawler, '/courses'),
        ]);
        self::assertSame(403, $client->getResponse()->getStatusCode());
        self::assertNull($this->courseBySlug('forbidden-course'));

        $client->request('GET', '/courses/'.$adminCourseId);
        self::assertSame(403, $client->getResponse()->getStatusCode());

        $crawler = $client->request('GET', '/courses/'.$ownCourseId);
        $client->request('POST', '/courses/'.$adminCourseId, [
            'title' => 'Cross org update',
            'description' => 'Should not apply.',
            'slug' => 'org-b-course',
            'visibility' => 'public',
            '_csrf_token' => $this->csrfFromForm($crawler, '/courses/'.$ownCourseId),
        ]);
        self::assertSame(403, $client->getResponse()->getStatusCode());

        $adminCourse = $this->courseBySlug('org-b-course');
        self::assertNotNull($adminCourse);
        $version = $this->latestVersion($adminCourseId);
        self::assertNotNull($version);
        self::assertSame('Org B Course', $this->asString($version['title']));
    }

    public function testCsrfTokenIsRequiredForCourseMutations(): void
    {
        $client = $this->client();
        $adminId = $this->createVerifiedUser('admin@example.com', 'platform:admin');
        $organisationId = $this->createOrganisationDirect('Acme', 'acme');
        $this->addMembershipDirect($organisationId, $adminId);
        $this->login($client, 'admin@example.com');

        $client->request('POST', '/courses', [
            'organisation_id' => $organisationId,
            'title' => 'No Token',
            'description' => '',
            'slug' => 'no-token',
        ]);
        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertSame(0, $this->countRows('courses'));

        $courseId = $this->createCourseViaHttp($client, $organisationId, 'Guarded Course', 'guarded-course');

        $client->request('POST', '/courses/'.$courseId.'/modules', [
            'title' => 'No Token Module',
            'description' => '',
            'is_required' => '1',
        ]);
        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertSame(0, $this->countRows('course_modules'));
    }

    public function testInstructorEditsDraftsButCannotPublishOrArchive(): void
    {
        $client = $this->client();
        $instructorId = $this->createVerifiedUser('instructor@example.com', 'lms:instructor');
        $organisationId = $this->createOrganisationDirect('Acme', 'acme');
        $this->addMembershipDirect($organisationId, $instructorId);
        $this->login($client, 'instructor@example.com');

        $courseId = $this->createCourseViaHttp($client, $organisationId, 'Draft Course', 'draft-course');

        $crawler = $client->request('GET', '/courses/'.$courseId);
        $client->request('POST', '/courses/'.$courseId, [
            'title' => 'Renamed Draft',
            'description' => 'Instructor edited.',
            'slug' => 'draft-course',
            'visibility' => 'organisation_only',
            '_csrf_token' => $this->csrfFromForm($crawler, '/courses/'.$courseId),
        ]);
        self::assertTrue($client->getResponse()->isRedirect());

        $version = $this->latestVersion($courseId);
        self::assertNotNull($version);
        self::assertSame('Renamed Draft', $this->asString($version['title']));

        $this->postLifecycle($client, $courseId, 'publish', [], false);
        self::assertSame(403, $client->getResponse()->getStatusCode());

        $this->postLifecycle($client, $courseId, 'archive', [], false);
        self::assertSame(403, $client->getResponse()->getStatusCode());

        self::assertSame('draft', $this->versionStatus($courseId));
    }

    protected function client(): KernelBrowser
    {
        self::ensureKernelShutdown();

        return parent::client();
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function publishedCourse(KernelBrowser $client, string $slug, string $title): array
    {
        $adminId = $this->createVerifiedUser('publisher@example.com', 'platform:admin');
        $organisationId = $this->createOrganisationDirect('Publisher Org', 'publisher-org');
        $this->addMembershipDirect($organisationId, $adminId);
        $this->login($client, 'publisher@example.com');

        $courseId = $this->createCourseViaHttp($client, $organisationId, $title, $slug);
        $moduleId = $this->addModuleViaHttp($client, $courseId, 'Required Module');
        $lessonId = $this->addLessonViaHttp($client, $courseId, $moduleId, 'Lesson One');

        $crawler = $client->request('GET', '/courses/'.$courseId);
        $client->request('POST', '/courses/'.$courseId.'/lessons/'.$lessonId.'/content', [
            'content_html' => '<p>Original content</p>',
            '_csrf_token' => $this->csrfFromForm(
                $crawler,
                '/courses/'.$courseId.'/lessons/'.$lessonId.'/content',
            ),
        ]);

        $this->postLifecycle($client, $courseId, 'submit');
        $this->postLifecycle($client, $courseId, 'publish');

        self::assertSame('published', $this->versionStatus($courseId));

        return [$courseId, $moduleId, $lessonId];
    }

    private function createCourseViaHttp(KernelBrowser $client, string $organisationId, string $title, string $slug): string
    {
        $crawler = $client->request('GET', '/courses/new');
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $client->request('POST', '/courses', [
            'organisation_id' => $organisationId,
            'title' => $title,
            'description' => 'Created by the functional test.',
            'slug' => $slug,
            '_csrf_token' => $this->csrfFromForm($crawler, '/courses'),
        ]);

        self::assertTrue($client->getResponse()->isRedirect());

        $course = $this->courseBySlug($slug);
        self::assertNotNull($course);

        return $this->asString($course['id'] ?? null);
    }

    private function addModuleViaHttp(KernelBrowser $client, string $courseId, string $title): string
    {
        $crawler = $client->request('GET', '/courses/'.$courseId);
        $client->request('POST', '/courses/'.$courseId.'/modules', [
            'title' => $title,
            'description' => 'Module created by the functional test.',
            'is_required' => '1',
            '_csrf_token' => $this->csrfFromForm($crawler, '/courses/'.$courseId.'/modules'),
        ]);

        self::assertTrue($client->getResponse()->isRedirect());

        $moduleId = $this->connection()->fetchOne(
            'SELECT m.id FROM course_modules m
             INNER JOIN course_versions v ON v.id = m.course_version_id
             WHERE v.course_id = :course_id AND m.title = :title',
            ['course_id' => $courseId, 'title' => $title],
        );
        self::assertIsString($moduleId);

        return $moduleId;
    }

    private function addLessonViaHttp(KernelBrowser $client, string $courseId, string $moduleId, string $title): string
    {
        $action = '/courses/'.$courseId.'/modules/'.$moduleId.'/lessons';
        $crawler = $client->request('GET', '/courses/'.$courseId);
        $client->request('POST', $action, [
            'title' => $title,
            '_csrf_token' => $this->csrfFromForm($crawler, $action),
        ]);

        self::assertTrue($client->getResponse()->isRedirect());

        $lessonId = $this->connection()->fetchOne(
            'SELECT id FROM lessons WHERE course_module_id = :module_id AND title = :title',
            ['module_id' => $moduleId, 'title' => $title],
        );
        self::assertIsString($lessonId);

        return $lessonId;
    }

    /**
     * @param array<string, string> $fields
     */
    private function postLifecycle(
        KernelBrowser $client,
        string $courseId,
        string $suffix,
        array $fields = [],
        bool $expectRedirect = true,
    ): void {
        $action = '/courses/'.$courseId.(('' === $suffix) ? '' : '/'.$suffix);
        $crawler = $client->request('GET', '/courses/'.$courseId);
        $client->request('POST', $action, $fields + [
            '_csrf_token' => $this->csrfFromForm($crawler, $action),
        ]);

        if ($expectRedirect) {
            self::assertTrue($client->getResponse()->isRedirect());
        }
    }

    private function createVerifiedUser(string $email, ?string $roleCode = null): string
    {
        $container = self::getContainer();

        $users = $container->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);

        $uuid = $container->get(UuidGeneratorInterface::class);
        self::assertInstanceOf(UuidGeneratorInterface::class, $uuid);

        $clock = $container->get(ClockInterface::class);
        self::assertInstanceOf(ClockInterface::class, $clock);

        $hasher = $container->get(PasswordHasherInterface::class);
        self::assertInstanceOf(PasswordHasherInterface::class, $hasher);

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
        $roles = self::getContainer()->get(RoleRepositoryInterface::class);
        self::assertInstanceOf(RoleRepositoryInterface::class, $roles);

        $clock = self::getContainer()->get(ClockInterface::class);
        self::assertInstanceOf(ClockInterface::class, $clock);

        $roles->assign($userId, RoleIdentifier::fromString($roleCode), null, null, $clock->now());
    }

    private function assignOrganisationRole(string $userId, string $roleCode, string $organisationId): void
    {
        $roles = self::getContainer()->get(RoleRepositoryInterface::class);
        self::assertInstanceOf(RoleRepositoryInterface::class, $roles);

        $clock = self::getContainer()->get(ClockInterface::class);
        self::assertInstanceOf(ClockInterface::class, $clock);

        $roles->assign(
            UserId::fromString($userId),
            RoleIdentifier::fromString($roleCode),
            OrganisationId::fromString($organisationId),
            null,
            $clock->now(),
        );
    }

    private function createOrganisationDirect(string $name, string $slug): string
    {
        $organisations = self::getContainer()->get(OrganisationRepositoryInterface::class);
        self::assertInstanceOf(OrganisationRepositoryInterface::class, $organisations);

        $uuid = self::getContainer()->get(UuidGeneratorInterface::class);
        self::assertInstanceOf(UuidGeneratorInterface::class, $uuid);

        $clock = self::getContainer()->get(ClockInterface::class);
        self::assertInstanceOf(ClockInterface::class, $clock);

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
        $memberships = self::getContainer()->get(MembershipRepositoryInterface::class);
        self::assertInstanceOf(MembershipRepositoryInterface::class, $memberships);

        $uuid = self::getContainer()->get(UuidGeneratorInterface::class);
        self::assertInstanceOf(UuidGeneratorInterface::class, $uuid);

        $clock = self::getContainer()->get(ClockInterface::class);
        self::assertInstanceOf(ClockInterface::class, $clock);

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
            '_csrf_token' => $this->csrfToken($crawler),
        ]);

        self::assertTrue($client->getResponse()->isRedirect());

        $client->followRedirect();

        if (null !== $client->getResponse()->headers->get('Location')) {
            $client->followRedirect();
        }
    }

    private function csrfFromForm(Crawler $crawler, string $action): string
    {
        $nodes = $crawler->filter(sprintf('form[action="%s"] input[name="_csrf_token"]', $action));
        self::assertGreaterThan(0, $nodes->count(), sprintf('CSRF field for form "%s" is missing.', $action));

        $value = $nodes->first()->attr('value');
        self::assertNotNull($value);

        return $value;
    }

    private function csrfToken(Crawler $crawler): string
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
    private function courseBySlug(string $slug): ?array
    {
        $row = $this->connection()->fetchAssociative(
            'SELECT * FROM courses WHERE slug = :slug',
            ['slug' => $slug],
        );

        return false === $row ? null : $row;
    }

    /**
     * @return null|array<string, mixed>
     */
    private function latestVersion(string $courseId): ?array
    {
        $row = $this->connection()->fetchAssociative(
            'SELECT * FROM course_versions WHERE course_id = :course_id ORDER BY version_number DESC LIMIT 1',
            ['course_id' => $courseId],
        );

        return false === $row ? null : $row;
    }

    private function versionStatus(string $courseId): string
    {
        $version = $this->latestVersion($courseId);
        self::assertNotNull($version);

        return $this->asString($version['status'] ?? null);
    }

    /**
     * @return list<string>
     */
    private function moduleIds(string $courseId): array
    {
        $values = $this->connection()->fetchFirstColumn(
            'SELECT m.id FROM course_modules m
             INNER JOIN course_versions v ON v.id = m.course_version_id
             WHERE v.course_id = :course_id ORDER BY m.position',
            ['course_id' => $courseId],
        );

        return array_map(fn (mixed $value): string => $this->asString($value), $values);
    }

    /**
     * @return list<string>
     */
    private function moduleTitles(string $courseId): array
    {
        $values = $this->connection()->fetchFirstColumn(
            'SELECT m.title FROM course_modules m
             INNER JOIN course_versions v ON v.id = m.course_version_id
             WHERE v.course_id = :course_id ORDER BY m.position',
            ['course_id' => $courseId],
        );

        return array_map(fn (mixed $value): string => $this->asString($value), $values);
    }

    /**
     * @return list<int>
     */
    private function modulePositions(string $courseId): array
    {
        $values = $this->connection()->fetchFirstColumn(
            'SELECT m.position FROM course_modules m
             INNER JOIN course_versions v ON v.id = m.course_version_id
             WHERE v.course_id = :course_id ORDER BY m.position',
            ['course_id' => $courseId],
        );

        return array_map(fn (mixed $value): int => $this->asInt($value), $values);
    }

    /**
     * @return list<string>
     */
    private function lessonTitles(string $moduleId): array
    {
        $values = $this->connection()->fetchFirstColumn(
            'SELECT title FROM lessons WHERE course_module_id = :module_id ORDER BY position',
            ['module_id' => $moduleId],
        );

        return array_map(fn (mixed $value): string => $this->asString($value), $values);
    }

    /**
     * @return list<int>
     */
    private function lessonPositions(string $moduleId): array
    {
        $values = $this->connection()->fetchFirstColumn(
            'SELECT position FROM lessons WHERE course_module_id = :module_id ORDER BY position',
            ['module_id' => $moduleId],
        );

        return array_map(fn (mixed $value): int => $this->asInt($value), $values);
    }

    private function lessonHtml(string $lessonId): string
    {
        $value = $this->connection()->fetchOne(
            'SELECT content_html FROM lesson_contents WHERE lesson_id = :lesson_id',
            ['lesson_id' => $lessonId],
        );

        return $this->asString($value);
    }

    /**
     * @return list<string>
     */
    private function headingOrder(string $html, string $tag): array
    {
        $pattern = sprintf('#<%1$s>(.*?)</%1$s>#s', preg_quote($tag, '#'));

        if (0 === preg_match_all($pattern, $html, $matches)) {
            return [];
        }

        return array_map(static fn (string $value): string => trim($value), $matches[1]);
    }

    private function content(KernelBrowser $client): string
    {
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);

        return $content;
    }

    private function countRows(string $table): int
    {
        $count = $this->connection()->fetchOne('SELECT COUNT(*) FROM '.$table);

        return $this->asInt($count);
    }

    private function asInt(mixed $value): int
    {
        if (!is_numeric($value)) {
            self::fail('Expected a numeric value.');
        }

        return (int) $value;
    }

    private function asString(mixed $value): string
    {
        if (!\is_string($value)) {
            self::fail('Expected a string value.');
        }

        return $value;
    }
}
