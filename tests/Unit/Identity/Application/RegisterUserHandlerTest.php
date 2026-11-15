<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Identity\Application;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Identity\Application\Command\RegisterUserCommand;
use SovereignStack\Lms\Identity\Application\Handler\RegisterUserHandler;
use SovereignStack\Lms\Identity\Application\Security\UserTokenService;
use SovereignStack\Lms\Identity\Domain\Entity\User;
use SovereignStack\Lms\Identity\Domain\Enum\UserStatus;
use SovereignStack\Lms\Identity\Domain\Exception\EmailAlreadyRegisteredException;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Tests\Support\Fakes\FakePasswordHasher;
use SovereignStack\Lms\Tests\Support\Fakes\FakeTokenGenerator;
use SovereignStack\Lms\Tests\Support\Fakes\FakeTransactionManager;
use SovereignStack\Lms\Tests\Support\Fakes\FixedClock;
use SovereignStack\Lms\Tests\Support\Fakes\InMemoryRoleRepository;
use SovereignStack\Lms\Tests\Support\Fakes\InMemoryUserProfileRepository;
use SovereignStack\Lms\Tests\Support\Fakes\InMemoryUserRepository;
use SovereignStack\Lms\Tests\Support\Fakes\InMemoryUserTokenRepository;
use SovereignStack\Lms\Tests\Support\Fakes\RecordingAccountMailer;
use SovereignStack\Lms\Tests\Support\Fakes\RecordingAuditLogger;
use SovereignStack\Lms\Tests\Support\Fakes\SequentialUuidGenerator;

/**
 * @internal
 *
 * @coversNothing
 */
final class RegisterUserHandlerTest extends TestCase
{
    private const EMAIL = 'jane@example.com';
    private const PASSWORD = 'correct-horse-battery';

    private FixedClock $clock;
    private InMemoryUserRepository $users;
    private InMemoryUserProfileRepository $profiles;
    private InMemoryUserTokenRepository $tokens;
    private RecordingAccountMailer $mailer;
    private RecordingAuditLogger $audit;
    private FakeTransactionManager $transactions;
    private InMemoryRoleRepository $roles;
    private RegisterUserHandler $handler;

    protected function setUp(): void
    {
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-11-14T10:00:00+00:00'));
        $this->users = new InMemoryUserRepository();
        $this->profiles = new InMemoryUserProfileRepository();
        $this->tokens = new InMemoryUserTokenRepository();
        $this->mailer = new RecordingAccountMailer();
        $this->audit = new RecordingAuditLogger();
        $this->transactions = new FakeTransactionManager();
        $this->roles = new InMemoryRoleRepository();

        $tokenService = new UserTokenService($this->tokens, new FakeTokenGenerator(), new SequentialUuidGenerator(), $this->clock);

        $this->handler = new RegisterUserHandler(
            $this->users,
            $this->profiles,
            new FakePasswordHasher(),
            $tokenService,
            $this->mailer,
            $this->audit,
            $this->transactions,
            new SequentialUuidGenerator(),
            $this->clock,
            $this->roles,
        );
    }

    public function testRegistrationCreatesUserProfileTokenMailAuditAndTransaction(): void
    {
        $userId = ($this->handler)(new RegisterUserCommand(self::EMAIL, self::PASSWORD, 'Jane Doe'));

        $stored = $this->users->findById($userId);
        self::assertNotNull($stored);
        self::assertSame(self::EMAIL, $stored->email()->value());
        self::assertSame(UserStatus::PENDING_VERIFICATION, $stored->status());
        self::assertSame('hashed:'.self::PASSWORD, $stored->passwordHash());

        $profile = $this->profiles->findByUserId($userId);
        self::assertNotNull($profile);
        self::assertSame('Jane Doe', $profile->displayName());
        self::assertSame('UTC', $profile->preferredTz());

        self::assertSame(1, $this->transactions->calls);
        self::assertCount(1, $this->mailer->verifications);
        self::assertSame(1, $this->tokens->count());
        self::assertTrue($this->audit->hasEvent('user.registered'));

        self::assertSame([
            ['userId' => $userId->value(), 'role' => 'lms:learner', 'organisationId' => null],
        ], $this->roles->assignments);

        $assignedRole = $this->roles->rolesForUser($userId);
        self::assertCount(1, $assignedRole);
        self::assertSame('lms:learner', $assignedRole[0]->value());

        $token = $this->tokens->findByHash(hash('sha256', $this->mailer->verifications[0]['token']));
        self::assertNotNull($token);
    }

    public function testRegistrationFallsBackToEmailLocalPartForDisplayName(): void
    {
        $userId = ($this->handler)(new RegisterUserCommand(self::EMAIL, self::PASSWORD, '   '));

        $profile = $this->profiles->findByUserId($userId);
        self::assertNotNull($profile);
        self::assertSame('jane', $profile->displayName());
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $existing = User::register(
            UserId::fromString('01900000-0000-7000-8000-000000000999'),
            Email::fromString(self::EMAIL),
            'hashed:whatever',
            $this->clock->now(),
        );
        $this->users->save($existing);

        $this->expectException(EmailAlreadyRegisteredException::class);

        ($this->handler)(new RegisterUserCommand(self::EMAIL, self::PASSWORD, 'Jane Doe'));
    }

    public function testShortPasswordIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        ($this->handler)(new RegisterUserCommand(self::EMAIL, 'short', 'Jane Doe'));
    }

    public function testPasswordEqualToEmailIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        ($this->handler)(new RegisterUserCommand(self::EMAIL, self::EMAIL, 'Jane Doe'));
    }
}
