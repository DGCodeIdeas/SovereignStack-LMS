<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Functional;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use SovereignStack\Lms\Identity\Domain\Repository\UserRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Tests\Functional\Fixtures\DeterministicTokenGenerator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @internal
 *
 * @coversNothing
 */
final class AuthenticationFlowTest extends DatabaseWebTestCase
{
    private const PASSWORD = 'CorrectHorseBatteryStaple1';

    private const NEW_PASSWORD = 'AnotherStrongPassword2';

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncateDirectly(
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

    public function testRegistrationVerificationAndLogin(): void
    {
        $client = $this->client();
        $email = 'learner@example.com';

        $this->register($client, $email);
        $this->verifyEmail($client);

        $this->login($client, $email, self::PASSWORD);

        self::assertSame('/account', $client->getRequest()->getPathInfo());
        self::assertStringContainsString($email, $this->content($client));
        self::assertStringContainsString('active', $this->content($client));
    }

    public function testUnverifiedUserCannotLogIn(): void
    {
        $email = 'unverified@example.com';

        $this->register($this->client(), $email);

        $client = $this->client();
        $crawler = $client->request('GET', '/login');
        $client->request('POST', '/login', [
            'email' => $email,
            'password' => self::PASSWORD,
            '_csrf_token' => $this->csrfToken($crawler),
        ]);

        self::assertTrue($client->getResponse()->isRedirect());
        $client->followRedirect();

        self::assertStringContainsString('/login', $client->getRequest()->getPathInfo());
        self::assertStringContainsStringIgnoringCase('verify', $this->content($client));
    }

    public function testRegistrationRejectsMissingCsrfToken(): void
    {
        $client = $this->client();

        $client->request('POST', '/register', [
            'email' => 'csrf@example.com',
            'display_name' => 'CSRF User',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ]);

        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertSame(0, $this->countRows('users'));
    }

    public function testPasswordResetFlowAndSingleUseToken(): void
    {
        $client = $this->client();
        $email = 'reset@example.com';

        $this->register($client, $email);
        $this->verifyEmail($client);

        $crawler = $client->request('GET', '/reset-password');
        $client->request('POST', '/reset-password', [
            'email' => $email,
            '_csrf_token' => $this->csrfToken($crawler),
        ]);

        self::assertTrue($client->getResponse()->isRedirect());

        $resetToken = DeterministicTokenGenerator::lastToken();
        self::assertNotNull($resetToken);

        $crawler = $client->request('GET', '/reset-password/'.$resetToken);
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $client->request('POST', '/reset-password/'.$resetToken, [
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
            '_csrf_token' => $this->csrfToken($crawler),
        ]);

        self::assertTrue($client->getResponse()->isRedirect());

        $newClient = $this->client();
        $this->login($newClient, $email, self::NEW_PASSWORD);
        self::assertSame('/account', $newClient->getRequest()->getPathInfo());

        $oldPasswordClient = $this->client();
        $crawler = $oldPasswordClient->request('GET', '/login');
        $oldPasswordClient->request('POST', '/login', [
            'email' => $email,
            'password' => self::PASSWORD,
            '_csrf_token' => $this->csrfToken($crawler),
        ]);

        self::assertTrue($oldPasswordClient->getResponse()->isRedirect());
        $oldPasswordClient->followRedirect();
        self::assertStringContainsString('/login', $oldPasswordClient->getRequest()->getPathInfo());

        $reuseClient = $this->client();
        $reuseCrawler = $reuseClient->request('GET', '/reset-password/'.$resetToken);
        $reuseClient->request('POST', '/reset-password/'.$resetToken, [
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
            '_csrf_token' => $this->csrfToken($reuseCrawler),
        ]);

        self::assertTrue($reuseClient->getResponse()->isRedirect());
        $reuseClient->followRedirect();
        self::assertStringContainsString('/login', $reuseClient->getRequest()->getPathInfo());
        self::assertStringContainsStringIgnoringCase('invalid', $this->content($reuseClient));
    }

    public function testChangingPasswordInvalidatesCurrentSession(): void
    {
        $client = $this->client();
        $email = 'change@example.com';

        $this->register($client, $email);
        $this->verifyEmail($client);
        $this->login($client, $email, self::PASSWORD);

        $crawler = $client->request('GET', '/account/password');
        $client->request('POST', '/account/password', [
            'current_password' => self::PASSWORD,
            'new_password' => self::NEW_PASSWORD,
            'new_password_confirmation' => self::NEW_PASSWORD,
            '_csrf_token' => $this->csrfToken($crawler),
        ]);

        self::assertTrue($client->getResponse()->isRedirect());
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));

        $client->request('GET', '/account');

        self::assertTrue($client->getResponse()->isRedirect());
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testSuspendedUserCannotLogIn(): void
    {
        $client = $this->client();
        $email = 'suspended@example.com';

        $this->register($client, $email);
        $this->verifyEmail($client);

        $container = self::getContainer();

        $users = $container->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);

        $clock = $container->get(ClockInterface::class);
        self::assertInstanceOf(ClockInterface::class, $clock);

        $user = $users->findByEmail(Email::fromString($email));
        self::assertNotNull($user);

        $user->suspend($clock->now());
        $users->save($user);

        $loginClient = $this->client();
        $crawler = $loginClient->request('GET', '/login');
        $loginClient->request('POST', '/login', [
            'email' => $email,
            'password' => self::PASSWORD,
            '_csrf_token' => $this->csrfToken($crawler),
        ]);

        self::assertTrue($loginClient->getResponse()->isRedirect());
        $loginClient->followRedirect();

        self::assertStringContainsString('/login', $loginClient->getRequest()->getPathInfo());
        self::assertStringContainsStringIgnoringCase('not active', $this->content($loginClient));
    }

    public function testCorrelationIdRemainsPropagatedOnHealth(): void
    {
        $client = $this->client();
        $client->request('GET', '/health', server: ['HTTP_X_CORRELATION_ID' => 'gate-2a-correlation']);

        self::assertTrue($client->getResponse()->isSuccessful());
        self::assertSame('gate-2a-correlation', $client->getResponse()->headers->get('X-Correlation-ID'));
    }

    protected function client(): KernelBrowser
    {
        self::ensureKernelShutdown();

        return parent::client();
    }

    private function register(KernelBrowser $client, string $email): void
    {
        $crawler = $client->request('GET', '/register');
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $client->request('POST', '/register', [
            'email' => $email,
            'display_name' => 'Test User',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            '_csrf_token' => $this->csrfToken($crawler),
        ]);

        self::assertTrue($client->getResponse()->isRedirect());
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    private function verifyEmail(KernelBrowser $client): void
    {
        $token = DeterministicTokenGenerator::lastToken();
        self::assertNotNull($token);

        $client->request('GET', '/verify/email/'.$token);

        self::assertTrue($client->getResponse()->isRedirect());
        $client->followRedirect();

        self::assertStringContainsString('/login', $client->getRequest()->getPathInfo());
        self::assertStringContainsStringIgnoringCase('verified', $this->content($client));
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

    private function csrfToken(Crawler $crawler, string $name = '_csrf_token'): string
    {
        $nodes = $crawler->filter(sprintf('input[name="%s"]', $name));
        self::assertGreaterThan(0, $nodes->count(), sprintf('CSRF field "%s" is missing.', $name));

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

    private function truncateDirectly(string ...$tables): void
    {
        $params = (new DsnParser(['mysql' => 'pdo_mysql']))->parse($this->databaseUrl());
        $connection = DriverManager::getConnection($params);

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($tables as $table) {
                $connection->executeStatement('TRUNCATE TABLE '.$table);
            }
        } finally {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
            $connection->close();
        }
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
