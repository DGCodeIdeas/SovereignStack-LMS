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
final class OrganisationAccessTest extends DatabaseWebTestCase
{
    private const PASSWORD = 'CorrectHorseBatteryStaple1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncate(
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

    public function testLearnerCanViewOrganisationsAndAccount(): void
    {
        $userId = $this->createVerifiedUser('learner@example.com', 'lms:learner');

        $client = $this->client();
        $this->login($client, 'learner@example.com', self::PASSWORD);

        $client->request('GET', '/organisations');
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $client->request('GET', '/account');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('ROLE_LMS_LEARNER', $this->content($client));

        self::assertTrue($this->userHasRole($userId, 'lms:learner'));
    }

    public function testLearnerCannotCreateOrganisationOrViewMembers(): void
    {
        $learnerId = $this->createVerifiedUser('learner@example.com', 'lms:learner');
        $organisationId = $this->createOrganisationDirect('Acme', 'acme');
        $this->addMembershipDirect($organisationId, $learnerId);

        $client = $this->client();
        $this->login($client, 'learner@example.com', self::PASSWORD);

        $crawler = $client->request('GET', '/organisations');
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $client->request('POST', '/organisations', [
            'name' => 'Forbidden Org',
            'slug' => 'forbidden-org',
            '_csrf_token' => $this->csrfFromForm($crawler, '/organisations'),
        ]);

        self::assertSame(403, $client->getResponse()->getStatusCode());
        self::assertSame(1, $this->countRows('organisations'));

        $client->request('GET', '/organisations/'.$organisationId.'/members');
        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testPlatformAdminCreatesOrganisationAndManagesMembers(): void
    {
        $adminId = $this->createVerifiedUser('admin@example.com', 'platform:admin');
        $learnerId = $this->createVerifiedUser('member@example.com', 'lms:learner');

        $client = $this->client();
        $this->login($client, 'admin@example.com', self::PASSWORD);

        $crawler = $client->request('GET', '/organisations');
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $client->request('POST', '/organisations', [
            'name' => 'Contoso',
            'slug' => 'contoso',
            '_csrf_token' => $this->csrfFromForm($crawler, '/organisations'),
        ]);

        self::assertTrue($client->getResponse()->isRedirect());

        $organisation = $this->organisationBySlug('contoso');
        self::assertNotNull($organisation);
        $organisationId = $organisation->id()->value();

        self::assertStringContainsString(
            '/organisations/'.$organisationId.'/members',
            (string) $client->getResponse()->headers->get('Location'),
        );
        self::assertTrue($this->hasMembership($organisationId, $adminId));

        $client->request('GET', '/organisations');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('Contoso', $this->content($client));

        $crawler = $client->request('GET', '/organisations/'.$organisationId.'/members');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString($adminId, $this->content($client));

        $client->request('POST', '/organisations/'.$organisationId.'/members', [
            'user_id' => $learnerId,
            '_csrf_token' => $this->csrfFromForm($crawler, '/organisations/'.$organisationId.'/members'),
        ]);

        self::assertTrue($client->getResponse()->isRedirect());
        self::assertTrue($this->hasMembership($organisationId, $learnerId));

        $client->request('GET', '/organisations/'.$organisationId.'/members');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $content = $this->content($client);
        self::assertStringContainsString($adminId, $content);
        self::assertStringContainsString($learnerId, $content);
    }

    public function testCrossOrganisationIsolation(): void
    {
        $learnerId = $this->createVerifiedUser('learner@example.com', 'lms:learner');
        $orgAdminId = $this->createVerifiedUser('org-admin@example.com', 'lms:learner');
        $otherMemberId = $this->createVerifiedUser('other@example.com', 'lms:learner');

        $orgA = $this->createOrganisationDirect('Org A', 'org-a');
        $orgB = $this->createOrganisationDirect('Org B', 'org-b');

        $this->addMembershipDirect($orgA, $learnerId);
        $this->addMembershipDirect($orgA, $orgAdminId);
        $this->addMembershipDirect($orgB, $otherMemberId);

        $this->assignOrganisationRole($orgAdminId, 'lms:admin', $orgA);

        $learnerClient = $this->client();
        $this->login($learnerClient, 'learner@example.com', self::PASSWORD);

        $learnerClient->request('GET', '/organisations/'.$orgB.'/members');
        self::assertSame(403, $learnerClient->getResponse()->getStatusCode());

        $adminClient = $this->client();
        $this->login($adminClient, 'org-admin@example.com', self::PASSWORD);

        $adminClient->request('GET', '/organisations/'.$orgA.'/members');
        self::assertSame(200, $adminClient->getResponse()->getStatusCode());

        $content = $this->content($adminClient);
        self::assertStringContainsString($learnerId, $content);
        self::assertStringContainsString($orgAdminId, $content);
        self::assertStringNotContainsString($otherMemberId, $content);
    }

    public function testCsrfIsRequiredForMutations(): void
    {
        $this->createVerifiedUser('admin@example.com', 'platform:admin');
        $learnerId = $this->createVerifiedUser('member@example.com', 'lms:learner');

        $client = $this->client();
        $this->login($client, 'admin@example.com', self::PASSWORD);

        $client->request('POST', '/organisations', [
            'name' => 'No Token',
            'slug' => 'no-token',
        ]);

        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertSame(0, $this->countRows('organisations'));

        $organisationId = $this->createOrganisationDirect('Acme', 'acme');

        $client->request('POST', '/organisations/'.$organisationId.'/members', [
            'user_id' => $learnerId,
        ]);

        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertSame(0, $this->countRows('organisation_memberships'));
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

    private function assignRole(UserId $userId, string $roleCode): void
    {
        $container = self::getContainer();

        $roles = $container->get(RoleRepositoryInterface::class);
        self::assertInstanceOf(RoleRepositoryInterface::class, $roles);

        $clock = $container->get(ClockInterface::class);
        self::assertInstanceOf(ClockInterface::class, $clock);

        $roles->assign($userId, RoleIdentifier::fromString($roleCode), null, null, $clock->now());
    }

    private function assignOrganisationRole(string $userId, string $roleCode, string $organisationId): void
    {
        $container = self::getContainer();

        $roles = $container->get(RoleRepositoryInterface::class);
        self::assertInstanceOf(RoleRepositoryInterface::class, $roles);

        $clock = $container->get(ClockInterface::class);
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
        $container = self::getContainer();

        $organisations = $container->get(OrganisationRepositoryInterface::class);
        self::assertInstanceOf(OrganisationRepositoryInterface::class, $organisations);

        $uuid = $container->get(UuidGeneratorInterface::class);
        self::assertInstanceOf(UuidGeneratorInterface::class, $uuid);

        $clock = $container->get(ClockInterface::class);
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
        $container = self::getContainer();

        $memberships = $container->get(MembershipRepositoryInterface::class);
        self::assertInstanceOf(MembershipRepositoryInterface::class, $memberships);

        $uuid = $container->get(UuidGeneratorInterface::class);
        self::assertInstanceOf(UuidGeneratorInterface::class, $uuid);

        $clock = $container->get(ClockInterface::class);
        self::assertInstanceOf(ClockInterface::class, $clock);

        $memberships->save(Membership::join(
            $uuid->generate(),
            OrganisationId::fromString($organisationId),
            UserIdReference::fromString($userId),
            $clock->now(),
        ));
    }

    private function organisationBySlug(string $slug): ?Organisation
    {
        $organisations = self::getContainer()->get(OrganisationRepositoryInterface::class);
        self::assertInstanceOf(OrganisationRepositoryInterface::class, $organisations);

        return $organisations->findBySlug($slug);
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

    private function login(KernelBrowser $client, string $email, string $password): void
    {
        $crawler = $client->request('GET', '/login');

        $client->request('POST', '/login', [
            'email' => $email,
            'password' => $password,
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

    private function countRows(string $table): int
    {
        $count = $this->connection()->fetchOne('SELECT COUNT(*) FROM '.$table);

        if (!is_numeric($count)) {
            self::fail('Expected a numeric row count.');
        }

        return (int) $count;
    }
}
