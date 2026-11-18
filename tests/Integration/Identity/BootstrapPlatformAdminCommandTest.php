<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Identity;

use SovereignStack\Lms\Tests\Integration\DatabaseIntegrationTestCase;
use SovereignStack\Lms\Tests\Integration\IdentityOrganisationFixtures;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * @coversNothing
 */
final class BootstrapPlatformAdminCommandTest extends DatabaseIntegrationTestCase
{
    use IdentityOrganisationFixtures;

    private const COMMAND = 'app:bootstrap-platform-admin';
    private const PASSWORD = 'Correct-Horse-Battery-9';

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncate(
            'audit_events',
            'user_tokens',
            'user_profiles',
            'user_roles',
            'organisation_memberships',
            'users',
            'organisations',
        );
    }

    public function testBootstrapCreatesActivePlatformAdministratorOnlyOnce(): void
    {
        $email = 'platform.admin@example.com';

        [$status, $output] = $this->runCommand($email, 'Platform Owner', self::PASSWORD, self::PASSWORD);

        self::assertSame(Command::SUCCESS, $status, $output);
        self::assertStringNotContainsString(self::PASSWORD, $output);

        $user = $this->connection()->fetchAssociative(
            'SELECT * FROM users WHERE email = :email',
            ['email' => $email],
        );
        self::assertIsArray($user);
        self::assertSame('active', $this->rowString($user, 'status'));
        self::assertNotNull($user['email_verified_at'] ?? null);

        $passwordHash = $this->rowString($user, 'password_hash');
        self::assertNotSame(self::PASSWORD, $passwordHash);
        self::assertStringNotContainsString($passwordHash, $output);

        $profile = $this->connection()->fetchAssociative(
            'SELECT display_name FROM user_profiles WHERE user_id = :user_id',
            ['user_id' => $this->rowString($user, 'id')],
        );
        self::assertIsArray($profile);
        self::assertSame('Platform Owner', $this->rowString($profile, 'display_name'));

        self::assertSame(1, $this->fetchInt(
            'SELECT COUNT(*)
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id AND r.code = :role AND ur.organisation_id IS NULL',
            ['user_id' => $this->rowString($user, 'id'), 'role' => 'platform:admin'],
        ));

        $audit = $this->connection()->fetchAssociative(
            "SELECT event_data FROM audit_events WHERE event_type = 'platform.bootstrap_completed'",
        );
        self::assertIsArray($audit);
        $eventData = $this->rowString($audit, 'event_data');
        self::assertStringContainsString('platform:admin', $eventData);
        self::assertStringNotContainsString(self::PASSWORD, $eventData);
        self::assertStringNotContainsString($passwordHash, $eventData);

        [$secondStatus, $secondOutput] = $this->runCommand(
            'another.admin@example.com',
            'Another Admin',
            self::PASSWORD,
            self::PASSWORD,
        );

        self::assertSame(Command::FAILURE, $secondStatus);
        self::assertStringContainsString('platform administrator already exists', $secondOutput);
        self::assertSame(1, $this->fetchInt('SELECT COUNT(*) FROM users'));
        self::assertSame(1, $this->fetchInt('SELECT COUNT(*) FROM user_roles'));
        self::assertSame(1, $this->fetchInt(
            "SELECT COUNT(*) FROM audit_events WHERE event_type = 'platform.bootstrap_completed'",
        ));
    }

    public function testBootstrapRejectsExistingEmail(): void
    {
        $email = 'existing.user@example.com';
        $this->insertUser($email);

        [$status, $output] = $this->runCommand($email, 'Existing User', self::PASSWORD, self::PASSWORD);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('already registered', $output);
        self::assertSame(0, $this->fetchInt(
            "SELECT COUNT(*) FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE r.code = 'platform:admin'",
        ));
    }

    public function testBootstrapRejectsMismatchedPasswordConfirmation(): void
    {
        [$status, $output] = $this->runCommand(
            'platform.admin@example.com',
            'Platform Owner',
            self::PASSWORD,
            'Different-Password-123',
        );

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('confirmation does not match', $output);
        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM users'));
    }

    /**
     * @return array{int, string}
     */
    private function runCommand(string $email, string $displayName, string $password, string $confirmation): array
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find(self::COMMAND));
        $tester->setInputs([$email, $displayName, $password, $confirmation]);
        $status = $tester->execute([]);

        return [$status, $tester->getDisplay()];
    }
}
