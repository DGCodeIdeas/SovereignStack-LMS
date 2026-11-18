<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Functional;

use SovereignStack\Lms\Identity\Application\Contract\PasswordHasherInterface;
use SovereignStack\Lms\Identity\Domain\Entity\User;
use SovereignStack\Lms\Identity\Domain\Enum\UserStatus;
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
final class AdminOperationsTest extends DatabaseWebTestCase
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
            'course_completions',
            'lesson_progress',
            'module_progress',
            'enrollments',
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

    public function testPlatformAdminCanListSearchAndOpenUsersWhileOthersAreForbidden(): void
    {
        $adminId = $this->createVerifiedUser('admin@example.com', RoleIdentifier::PLATFORM_ADMIN);
        $learnerId = $this->createVerifiedUser('learner@example.com', RoleIdentifier::LMS_LEARNER);

        $adminClient = $this->client();
        $this->login($adminClient, 'admin@example.com');

        $adminClient->request('GET', '/admin/users');
        self::assertSame(200, $adminClient->getResponse()->getStatusCode());
        $content = $this->content($adminClient);
        self::assertStringContainsString('admin@example.com', $content);
        self::assertStringContainsString('learner@example.com', $content);
        self::assertStringContainsString('href="/admin/users/'.$learnerId.'"', $content);
        self::assertStringNotContainsString('$2y$', $content);
        self::assertStringNotContainsString('password_hash', $content);

        $adminClient->request('GET', '/admin/users?q=learner');
        self::assertSame(200, $adminClient->getResponse()->getStatusCode());
        $content = $this->content($adminClient);
        self::assertStringContainsString('learner@example.com', $content);
        self::assertStringNotContainsString('admin@example.com', $content);

        $adminClient->request('GET', '/admin/users/'.$learnerId);
        self::assertSame(200, $adminClient->getResponse()->getStatusCode());
        $content = $this->content($adminClient);
        self::assertStringContainsString('learner@example.com', $content);
        self::assertStringContainsString('(lms:learner)', $content);
        self::assertStringNotContainsString('$2y$', $content);
        self::assertStringNotContainsString('password_hash', $content);

        $nonPlatformId = $this->createVerifiedUser('lms-admin@example.com', RoleIdentifier::LMS_ADMIN);
        $nonPlatformClient = $this->client();
        $this->login($nonPlatformClient, 'lms-admin@example.com');

        $nonPlatformClient->request('GET', '/admin/users');
        self::assertSame(403, $nonPlatformClient->getResponse()->getStatusCode());

        $nonPlatformClient->request('GET', '/admin/users/'.$learnerId);
        self::assertSame(403, $nonPlatformClient->getResponse()->getStatusCode());

        $crawler = $nonPlatformClient->request('GET', '/account');
        self::assertSame(200, $nonPlatformClient->getResponse()->getStatusCode());
        self::assertStringNotContainsString('href="/admin/users"', $this->content($nonPlatformClient));
        self::assertNotSame($adminId, $nonPlatformId);
        self::assertNotSame($adminId, $learnerId);
    }

    public function testRoleCanBeAssignedAndRevokedAndLastPlatformAdminIsProtected(): void
    {
        $adminId = $this->createVerifiedUser('admin@example.com', RoleIdentifier::PLATFORM_ADMIN);
        $learnerId = $this->createVerifiedUser('learner@example.com', RoleIdentifier::LMS_LEARNER);

        $client = $this->client();
        $this->login($client, 'admin@example.com');

        $crawler = $client->request('GET', '/admin/users/'.$learnerId);
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $client->request('POST', '/admin/users/'.$learnerId.'/roles', [
            'role_code' => RoleIdentifier::LMS_INSTRUCTOR,
            '_csrf_token' => $this->csrfFromForm($crawler, '/admin/users/'.$learnerId.'/roles'),
        ]);

        self::assertTrue($client->getResponse()->isRedirect());
        self::assertTrue($this->userHasRole($learnerId, RoleIdentifier::LMS_INSTRUCTOR));

        $crawler = $client->request('GET', '/admin/users/'.$learnerId);
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $client->request('POST', '/admin/users/'.$learnerId.'/roles/revoke', [
            'role_code' => RoleIdentifier::LMS_INSTRUCTOR,
            'organisation_id' => '',
            '_csrf_token' => $this->csrfFromForm($crawler, '/admin/users/'.$learnerId.'/roles/revoke'),
        ]);

        self::assertTrue($client->getResponse()->isRedirect());
        self::assertFalse($this->userHasRole($learnerId, RoleIdentifier::LMS_INSTRUCTOR));

        $client->request('GET', '/admin/users/'.$adminId);
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $crawler = $client->getCrawler();

        $client->request('POST', '/admin/users/'.$adminId.'/roles/revoke', [
            'role_code' => RoleIdentifier::PLATFORM_ADMIN,
            'organisation_id' => '',
            '_csrf_token' => $this->csrfFromForm($crawler, '/admin/users/'.$adminId.'/roles/revoke'),
        ]);

        self::assertTrue($client->getResponse()->isRedirect());
        $client->followRedirect();
        self::assertStringContainsString('last platform administrator', strtolower($this->content($client)));
        self::assertTrue($this->userHasRole($adminId, RoleIdentifier::PLATFORM_ADMIN));
    }

    public function testRoleCanBeAssignedWithOrganisationScope(): void
    {
        $adminId = $this->createVerifiedUser('admin@example.com', RoleIdentifier::PLATFORM_ADMIN);
        $learnerId = $this->createVerifiedUser('learner@example.com', RoleIdentifier::LMS_LEARNER);
        $organisationId = $this->createOrganisationDirect('Acme', 'acme');
        $this->addMembershipDirect($organisationId, $adminId);
        $this->addMembershipDirect($organisationId, $learnerId);

        $client = $this->client();
        $this->login($client, 'admin@example.com');

        $crawler = $client->request('GET', '/admin/users/'.$learnerId);
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $client->request('POST', '/admin/users/'.$learnerId.'/roles', [
            'role_code' => RoleIdentifier::LMS_INSTRUCTOR,
            'organisation_id' => $organisationId,
            '_csrf_token' => $this->csrfFromForm($crawler, '/admin/users/'.$learnerId.'/roles'),
        ]);

        self::assertTrue($client->getResponse()->isRedirect());

        $roles = self::getContainer()->get(RoleRepositoryInterface::class);
        self::assertInstanceOf(RoleRepositoryInterface::class, $roles);
        $found = false;
        foreach ($roles->assignmentsForUser(UserId::fromString($learnerId)) as $assignment) {
            if (RoleIdentifier::LMS_INSTRUCTOR === $assignment['roleCode']
                && $assignment['organisationId'] === $organisationId) {
                $found = true;

                break;
            }
        }
        self::assertTrue($found);
    }

    public function testCsrfTokenIsRequiredForAdminAndInstructorMutations(): void
    {
        $adminId = $this->createVerifiedUser('admin@example.com', RoleIdentifier::PLATFORM_ADMIN);
        $learnerId = $this->createVerifiedUser('learner@example.com', RoleIdentifier::LMS_LEARNER);
        $organisationId = $this->createOrganisationDirect('Acme', 'acme');
        $this->addMembershipDirect($organisationId, $adminId);

        $client = $this->client();
        $this->login($client, 'admin@example.com');
        $courseId = $this->createCourseViaHttp($client, $organisationId, 'CSRF Course', 'csrf-course');

        $client->request('POST', '/admin/users/'.$learnerId.'/roles', [
            'role_code' => RoleIdentifier::LMS_INSTRUCTOR,
        ]);
        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertFalse($this->userHasRole($learnerId, RoleIdentifier::LMS_INSTRUCTOR));

        $client->request('POST', '/admin/users/'.$learnerId.'/roles/revoke', [
            'role_code' => RoleIdentifier::LMS_LEARNER,
            'organisation_id' => '',
        ]);
        self::assertSame(400, $client->getResponse()->getStatusCode());

        $client->request('POST', '/admin/users/'.$learnerId.'/suspend');
        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertSame(UserStatus::ACTIVE, $this->userStatus($learnerId));

        $client->request('POST', '/admin/users/'.$learnerId.'/deactivate');
        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertSame(UserStatus::ACTIVE, $this->userStatus($learnerId));

        $client->request('POST', '/courses/'.$courseId.'/instructors', [
            'instructor_user_id' => $learnerId,
            'effective_from' => '2000-01-01T00:00',
        ]);
        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertSame(0, $this->courseInstructorCount($courseId));
    }

    public function testSuspendingUserInvalidatesLiveSessionAndBlocksLogin(): void
    {
        $adminId = $this->createVerifiedUser('admin@example.com', RoleIdentifier::PLATFORM_ADMIN);
        $learnerId = $this->createVerifiedUser('suspended@example.com', RoleIdentifier::LMS_LEARNER);

        $learnerClient = $this->client();
        $this->login($learnerClient, 'suspended@example.com');
        $learnerClient->request('GET', '/account');
        self::assertSame(200, $learnerClient->getResponse()->getStatusCode());

        $adminClient = $this->client();
        $this->login($adminClient, 'admin@example.com');
        $crawler = $adminClient->request('GET', '/admin/users/'.$learnerId);
        self::assertSame(200, $adminClient->getResponse()->getStatusCode());

        $adminClient->request('POST', '/admin/users/'.$learnerId.'/suspend', [
            '_csrf_token' => $this->csrfFromForm($crawler, '/admin/users/'.$learnerId.'/suspend'),
        ]);

        self::assertTrue($adminClient->getResponse()->isRedirect());
        self::assertStringContainsString(
            '/admin/users/'.$learnerId,
            (string) $adminClient->getResponse()->headers->get('Location'),
        );
        self::assertSame(UserStatus::SUSPENDED, $this->userStatus($learnerId));

        $learnerClient->request('GET', '/account');
        self::assertTrue($learnerClient->getResponse()->isRedirect());
        self::assertStringContainsString('/login', (string) $learnerClient->getResponse()->headers->get('Location'));

        $loginClient = $this->client();
        $crawler = $loginClient->request('GET', '/login');
        $loginClient->request('POST', '/login', [
            'email' => 'suspended@example.com',
            'password' => self::PASSWORD,
            '_csrf_token' => $this->csrfToken($crawler),
        ]);

        self::assertTrue($loginClient->getResponse()->isRedirect());
        $loginClient->followRedirect();
        self::assertStringContainsStringIgnoringCase('not active', $this->content($loginClient));
        self::assertNotSame($adminId, $learnerId);
    }

    public function testDeactivatingUserInvalidatesLiveSessionAndBlocksLogin(): void
    {
        $this->createVerifiedUser('admin@example.com', RoleIdentifier::PLATFORM_ADMIN);
        $learnerId = $this->createVerifiedUser('deactivated@example.com', RoleIdentifier::LMS_LEARNER);

        $learnerClient = $this->client();
        $this->login($learnerClient, 'deactivated@example.com');
        $learnerClient->request('GET', '/account');
        self::assertSame(200, $learnerClient->getResponse()->getStatusCode());

        $adminClient = $this->client();
        $this->login($adminClient, 'admin@example.com');
        $crawler = $adminClient->request('GET', '/admin/users/'.$learnerId);
        self::assertSame(200, $adminClient->getResponse()->getStatusCode());

        $adminClient->request('POST', '/admin/users/'.$learnerId.'/deactivate', [
            '_csrf_token' => $this->csrfFromForm($crawler, '/admin/users/'.$learnerId.'/deactivate'),
        ]);

        self::assertTrue($adminClient->getResponse()->isRedirect());
        self::assertStringContainsString(
            '/admin/users/'.$learnerId,
            (string) $adminClient->getResponse()->headers->get('Location'),
        );
        self::assertSame(UserStatus::DEACTIVATED, $this->userStatus($learnerId));

        $learnerClient->request('GET', '/account');
        self::assertTrue($learnerClient->getResponse()->isRedirect());
        self::assertStringContainsString('/login', (string) $learnerClient->getResponse()->headers->get('Location'));

        $loginClient = $this->client();
        $crawler = $loginClient->request('GET', '/login');
        $loginClient->request('POST', '/login', [
            'email' => 'deactivated@example.com',
            'password' => self::PASSWORD,
            '_csrf_token' => $this->csrfToken($crawler),
        ]);

        self::assertTrue($loginClient->getResponse()->isRedirect());
        $loginClient->followRedirect();
        self::assertStringContainsStringIgnoringCase('not active', $this->content($loginClient));
        self::assertSame(UserStatus::DEACTIVATED, $this->userStatus($learnerId));
    }

    public function testReactivatingSuspendedUserRestoresLoginAndAudits(): void
    {
        $this->createVerifiedUser('admin@example.com', RoleIdentifier::PLATFORM_ADMIN);
        $learnerId = $this->createVerifiedUser('reactivate-suspended@example.com', RoleIdentifier::LMS_LEARNER);

        $adminClient = $this->client();
        $this->login($adminClient, 'admin@example.com');
        $crawler = $adminClient->request('GET', '/admin/users/'.$learnerId);
        self::assertSame(200, $adminClient->getResponse()->getStatusCode());

        $adminClient->request('POST', '/admin/users/'.$learnerId.'/suspend', [
            '_csrf_token' => $this->csrfFromForm($crawler, '/admin/users/'.$learnerId.'/suspend'),
        ]);
        self::assertTrue($adminClient->getResponse()->isRedirect());
        self::assertSame(UserStatus::SUSPENDED, $this->userStatus($learnerId));

        $crawler = $adminClient->request('GET', '/admin/users/'.$learnerId);
        self::assertSame(200, $adminClient->getResponse()->getStatusCode());

        $adminClient->request('POST', '/admin/users/'.$learnerId.'/reactivate', [
            '_csrf_token' => $this->csrfFromForm($crawler, '/admin/users/'.$learnerId.'/reactivate'),
        ]);

        self::assertTrue($adminClient->getResponse()->isRedirect());
        self::assertStringContainsString(
            '/admin/users/'.$learnerId,
            (string) $adminClient->getResponse()->headers->get('Location'),
        );
        self::assertSame(UserStatus::ACTIVE, $this->userStatus($learnerId));
        self::assertTrue($this->hasAuditEvent('user.reactivated', $learnerId));

        $learnerClient = $this->client();
        $this->login($learnerClient, 'reactivate-suspended@example.com');
        $learnerClient->request('GET', '/account');
        self::assertSame(200, $learnerClient->getResponse()->getStatusCode());
    }

    public function testReactivatingDeactivatedUserRestoresLoginAndAudits(): void
    {
        $this->createVerifiedUser('admin@example.com', RoleIdentifier::PLATFORM_ADMIN);
        $learnerId = $this->createVerifiedUser('reactivate-deactivated@example.com', RoleIdentifier::LMS_LEARNER);

        $adminClient = $this->client();
        $this->login($adminClient, 'admin@example.com');
        $crawler = $adminClient->request('GET', '/admin/users/'.$learnerId);
        self::assertSame(200, $adminClient->getResponse()->getStatusCode());

        $adminClient->request('POST', '/admin/users/'.$learnerId.'/deactivate', [
            '_csrf_token' => $this->csrfFromForm($crawler, '/admin/users/'.$learnerId.'/deactivate'),
        ]);
        self::assertTrue($adminClient->getResponse()->isRedirect());
        self::assertSame(UserStatus::DEACTIVATED, $this->userStatus($learnerId));

        $crawler = $adminClient->request('GET', '/admin/users/'.$learnerId);
        self::assertSame(200, $adminClient->getResponse()->getStatusCode());

        $adminClient->request('POST', '/admin/users/'.$learnerId.'/reactivate', [
            '_csrf_token' => $this->csrfFromForm($crawler, '/admin/users/'.$learnerId.'/reactivate'),
        ]);

        self::assertTrue($adminClient->getResponse()->isRedirect());
        self::assertSame(UserStatus::ACTIVE, $this->userStatus($learnerId));
        self::assertTrue($this->hasAuditEvent('user.reactivated', $learnerId));

        $learnerClient = $this->client();
        $this->login($learnerClient, 'reactivate-deactivated@example.com');
        $learnerClient->request('GET', '/account');
        self::assertSame(200, $learnerClient->getResponse()->getStatusCode());
    }

    public function testAdminUserListFiltersByStatusAndRole(): void
    {
        $this->createVerifiedUser('admin@example.com', RoleIdentifier::PLATFORM_ADMIN);
        $suspendedId = $this->createVerifiedUser('suspended-filter@example.com', RoleIdentifier::LMS_LEARNER);
        $this->createVerifiedUser('instructor-filter@example.com', RoleIdentifier::LMS_INSTRUCTOR);
        $this->suspendDirectly($suspendedId);

        $client = $this->client();
        $this->login($client, 'admin@example.com');

        $client->request('GET', '/admin/users?status=suspended');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $content = $this->content($client);
        self::assertStringContainsString('suspended-filter@example.com', $content);
        self::assertStringNotContainsString('instructor-filter@example.com', $content);
        self::assertStringNotContainsString('admin@example.com', $content);

        $client->request('GET', '/admin/users?role='.RoleIdentifier::LMS_INSTRUCTOR);
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $content = $this->content($client);
        self::assertStringContainsString('instructor-filter@example.com', $content);
        self::assertStringNotContainsString('suspended-filter@example.com', $content);
        self::assertStringNotContainsString('admin@example.com', $content);
    }

    public function testAdminUserListPaginatesBeyondFirstPage(): void
    {
        $this->createVerifiedUser('admin@example.com', RoleIdentifier::PLATFORM_ADMIN);
        $emails = [];
        for ($index = 1; $index <= 51; ++$index) {
            $emails[] = sprintf('bulk-%02d@example.com', $index);
        }
        $this->createVerifiedUsers($emails, RoleIdentifier::LMS_LEARNER);

        $client = $this->client();
        $this->login($client, 'admin@example.com');

        $client->request('GET', '/admin/users');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $content = $this->content($client);
        self::assertStringContainsString('href="/admin/users?', $content);
        self::assertStringContainsString('page=2', $content);
        self::assertStringNotContainsString('page=0', $content);

        $client->request('GET', '/admin/users?page=2');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $content = $this->content($client);
        self::assertStringContainsString('page=1', $content);
        self::assertStringContainsString('bulk-', $content);
    }

    public function testPlatformAdminCannotSuspendOrDeactivateTheirOwnAccount(): void
    {
        $adminId = $this->createVerifiedUser('admin@example.com', RoleIdentifier::PLATFORM_ADMIN);
        $otherId = $this->createVerifiedUser('other@example.com', RoleIdentifier::LMS_LEARNER);

        $client = $this->client();
        $this->login($client, 'admin@example.com');

        $crawler = $client->request('GET', '/admin/users/'.$otherId);
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $client->request('POST', '/admin/users/'.strtoupper($adminId).'/suspend', [
            '_csrf_token' => $this->csrfFromForm($crawler, '/admin/users/'.$otherId.'/suspend'),
        ]);
        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertSame(UserStatus::ACTIVE, $this->userStatus($adminId));

        $client->request('POST', '/admin/users/'.strtoupper($adminId).'/deactivate', [
            '_csrf_token' => $this->csrfFromForm($crawler, '/admin/users/'.$otherId.'/deactivate'),
        ]);
        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertSame(UserStatus::ACTIVE, $this->userStatus($adminId));
    }

    public function testInstructorAssignmentIsVisibleAndRespectsEffectiveWindow(): void
    {
        $adminId = $this->createVerifiedUser('admin@example.com', RoleIdentifier::PLATFORM_ADMIN);
        $instructorId = $this->createVerifiedUser('instructor@example.com', RoleIdentifier::LMS_INSTRUCTOR);
        $organisationId = $this->createOrganisationDirect('Acme', 'acme');
        $this->addMembershipDirect($organisationId, $adminId);

        $adminClient = $this->client();
        $this->login($adminClient, 'admin@example.com');
        $courseId = $this->createCourseViaHttp($adminClient, $organisationId, 'Instructor Course', 'instructor-course');

        $crawler = $adminClient->request('GET', '/courses/'.$courseId);
        self::assertSame(200, $adminClient->getResponse()->getStatusCode());

        $adminClient->request('POST', '/courses/'.$courseId.'/instructors', [
            'instructor_user_id' => $instructorId,
            'effective_from' => '2999-01-01T00:00',
            'effective_until' => '',
            '_csrf_token' => $this->csrfFromForm($crawler, '/courses/'.$courseId.'/instructors'),
        ]);
        self::assertTrue($adminClient->getResponse()->isRedirect());
        self::assertSame(1, $this->courseInstructorCount($courseId));

        $adminClient->request('GET', '/courses/'.$courseId);
        self::assertSame(200, $adminClient->getResponse()->getStatusCode());
        self::assertStringContainsString('Instructors', $this->content($adminClient));
        self::assertStringContainsString($instructorId, $this->content($adminClient));

        $instructorClient = $this->client();
        $this->login($instructorClient, 'instructor@example.com');
        self::assertSame(403, $this->postCourseUpdate($instructorClient, $courseId, 'instructor-course', 'Future Edit'));
        self::assertSame('Instructor Course', $this->latestVersionTitle($courseId));

        $crawler = $adminClient->request('GET', '/courses/'.$courseId);
        $adminClient->request('POST', '/courses/'.$courseId.'/instructors', [
            'instructor_user_id' => $instructorId,
            'effective_from' => '2000-01-01T00:00',
            'effective_until' => '',
            '_csrf_token' => $this->csrfFromForm($crawler, '/courses/'.$courseId.'/instructors'),
        ]);
        self::assertTrue($adminClient->getResponse()->isRedirect());
        self::assertSame(2, $this->courseInstructorCount($courseId));

        self::assertSame(302, $this->postCourseUpdate($instructorClient, $courseId, 'instructor-course', 'Current Edit'));
        self::assertSame('Current Edit', $this->latestVersionTitle($courseId));

        $crawler = $adminClient->request('GET', '/courses/'.$courseId);
        $adminClient->request('POST', '/courses/'.$courseId.'/instructors', [
            'instructor_user_id' => $instructorId,
            'effective_from' => '2000-01-01T00:00',
            'effective_until' => '2000-01-02T00:00',
            '_csrf_token' => $this->csrfFromForm($crawler, '/courses/'.$courseId.'/instructors'),
        ]);
        self::assertTrue($adminClient->getResponse()->isRedirect());
        self::assertSame(3, $this->courseInstructorCount($courseId));

        self::assertSame(403, $this->postCourseUpdate($instructorClient, $courseId, 'instructor-course', 'Expired Edit'));
        self::assertSame('Current Edit', $this->latestVersionTitle($courseId));
        self::assertNotSame($adminId, $instructorId);
    }

    public function testOrganisationCreateAndAddMemberWorkAndCrossOrganisationAccessIsDenied(): void
    {
        $adminId = $this->createVerifiedUser('admin@example.com', RoleIdentifier::PLATFORM_ADMIN);
        $learnerId = $this->createVerifiedUser('learner@example.com', RoleIdentifier::LMS_LEARNER);

        $client = $this->client();
        $this->login($client, 'admin@example.com');

        $crawler = $client->request('GET', '/organisations');
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $client->request('POST', '/organisations', [
            'name' => 'Acme',
            'slug' => 'acme',
            '_csrf_token' => $this->csrfFromForm($crawler, '/organisations'),
        ]);
        self::assertTrue($client->getResponse()->isRedirect());

        $organisation = $this->organisationBySlug('acme');
        self::assertNotNull($organisation);
        $organisationId = $organisation->id()->value();
        self::assertTrue($this->hasMembership($organisationId, $adminId));

        $crawler = $client->request('GET', '/organisations/'.$organisationId.'/members');
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $client->request('POST', '/organisations/'.$organisationId.'/members', [
            'user_id' => $learnerId,
            '_csrf_token' => $this->csrfFromForm($crawler, '/organisations/'.$organisationId.'/members'),
        ]);
        self::assertTrue($client->getResponse()->isRedirect());
        self::assertTrue($this->hasMembership($organisationId, $learnerId));

        $otherOrganisationId = $this->createOrganisationDirect('Other', 'other');
        $learnerClient = $this->client();
        $this->login($learnerClient, 'learner@example.com');
        $learnerClient->request('GET', '/organisations/'.$otherOrganisationId.'/members');
        self::assertSame(403, $learnerClient->getResponse()->getStatusCode());
    }

    protected function client(): KernelBrowser
    {
        self::ensureKernelShutdown();

        return parent::client();
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

    /**
     * @param list<string> $emails
     */
    private function createVerifiedUsers(array $emails, ?string $roleCode = null): void
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
        $passwordHash = $hasher->hash(self::PASSWORD);

        foreach ($emails as $email) {
            $userId = UserId::fromString($uuid->generate());
            $user = User::register($userId, Email::fromString($email), $passwordHash, $now);
            $user->verifyEmail($now);
            $users->save($user);

            if (null !== $roleCode) {
                $this->assignRole($userId, $roleCode);
            }
        }
    }

    private function suspendDirectly(string $userId): void
    {
        $container = self::getContainer();

        $users = $container->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);

        $clock = $container->get(ClockInterface::class);
        self::assertInstanceOf(ClockInterface::class, $clock);

        $user = $users->findById(UserId::fromString($userId));
        self::assertNotNull($user);
        $user->suspend($clock->now());
        $users->save($user);
    }

    private function hasAuditEvent(string $eventType, string $resourceId): bool
    {
        $count = $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM audit_events WHERE event_type = :event_type AND resource_id = :resource_id',
            ['event_type' => $eventType, 'resource_id' => $resourceId],
        );

        return \is_numeric($count) && (int) $count > 0;
    }

    private function assignRole(UserId $userId, string $roleCode): void
    {
        $roles = self::getContainer()->get(RoleRepositoryInterface::class);
        self::assertInstanceOf(RoleRepositoryInterface::class, $roles);

        $clock = self::getContainer()->get(ClockInterface::class);
        self::assertInstanceOf(ClockInterface::class, $clock);

        $roles->assign($userId, RoleIdentifier::fromString($roleCode), null, null, $clock->now());
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

    private function content(KernelBrowser $client): string
    {
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);

        return $content;
    }

    private function userHasRole(string $userId, string $roleCode): bool
    {
        $roles = self::getContainer()->get(RoleRepositoryInterface::class);
        self::assertInstanceOf(RoleRepositoryInterface::class, $roles);

        foreach ($roles->rolesForUser(UserId::fromString($userId)) as $role) {
            if ($role->value() === $roleCode) {
                return true;
            }
        }

        return false;
    }

    private function userStatus(string $userId): UserStatus
    {
        $users = self::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);

        $user = $users->findById(UserId::fromString($userId));
        self::assertNotNull($user);

        return $user->status();
    }

    private function hasMembership(string $organisationId, string $userId): bool
    {
        $memberships = self::getContainer()->get(MembershipRepositoryInterface::class);
        self::assertInstanceOf(MembershipRepositoryInterface::class, $memberships);

        return null !== $memberships->findByOrganisationAndUser(
            OrganisationId::fromString($organisationId),
            UserIdReference::fromString($userId),
        );
    }

    private function organisationBySlug(string $slug): ?Organisation
    {
        $organisations = self::getContainer()->get(OrganisationRepositoryInterface::class);
        self::assertInstanceOf(OrganisationRepositoryInterface::class, $organisations);

        return $organisations->findBySlug($slug);
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

    private function postCourseUpdate(KernelBrowser $client, string $courseId, string $slug, string $title): int
    {
        $crawler = $client->request('GET', '/courses/'.$courseId);
        $client->request('POST', '/courses/'.$courseId, [
            'title' => $title,
            'description' => 'Updated by the functional test.',
            'slug' => $slug,
            'visibility' => 'organisation_only',
            '_csrf_token' => $this->csrfFromForm($crawler, '/courses/'.$courseId),
        ]);

        return $client->getResponse()->getStatusCode();
    }

    private function latestVersionTitle(string $courseId): ?string
    {
        $title = $this->connection()->fetchOne(
            'SELECT title FROM course_versions WHERE course_id = :course_id ORDER BY version_number DESC LIMIT 1',
            ['course_id' => $courseId],
        );

        return \is_string($title) ? $title : null;
    }

    private function courseInstructorCount(string $courseId): int
    {
        $count = $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM course_instructors WHERE course_id = :course_id',
            ['course_id' => $courseId],
        );

        if (!\is_numeric($count)) {
            self::fail('Expected a numeric course instructor count.');
        }

        return (int) $count;
    }

    private function asString(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }
}
