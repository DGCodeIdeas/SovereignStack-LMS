<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Identity\Application;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Identity\Application\Command\ChangePasswordCommand;
use SovereignStack\Lms\Identity\Application\Command\RequestPasswordResetCommand;
use SovereignStack\Lms\Identity\Application\Command\ResetPasswordCommand;
use SovereignStack\Lms\Identity\Application\Handler\ChangePasswordHandler;
use SovereignStack\Lms\Identity\Application\Handler\RequestPasswordResetHandler;
use SovereignStack\Lms\Identity\Application\Handler\ResetPasswordHandler;
use SovereignStack\Lms\Identity\Application\Security\UserTokenService;
use SovereignStack\Lms\Identity\Domain\Entity\User;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Tests\Support\Fakes\FakePasswordHasher;
use SovereignStack\Lms\Tests\Support\Fakes\FakeTokenGenerator;
use SovereignStack\Lms\Tests\Support\Fakes\FakeTransactionManager;
use SovereignStack\Lms\Tests\Support\Fakes\FixedClock;
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
final class PasswordResetHandlerTest extends TestCase
{
    private const EMAIL = 'jane@example.com';
    private const CURRENT = 'correct-horse-battery';
    private const NEW_PASSWORD = 'an-entirely-new-secret';

    private FixedClock $clock;
    private InMemoryUserRepository $users;
    private InMemoryUserTokenRepository $tokens;
    private UserTokenService $tokenService;
    private RecordingAccountMailer $mailer;
    private RecordingAuditLogger $audit;
    private FakePasswordHasher $hasher;
    private FakeTransactionManager $transactions;
    private UserId $userId;

    protected function setUp(): void
    {
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-11-14T10:00:00+00:00'));
        $this->users = new InMemoryUserRepository();
        $this->tokens = new InMemoryUserTokenRepository();
        $this->mailer = new RecordingAccountMailer();
        $this->audit = new RecordingAuditLogger();
        $this->hasher = new FakePasswordHasher();
        $this->transactions = new FakeTransactionManager();
        $this->tokenService = new UserTokenService($this->tokens, new FakeTokenGenerator(), new SequentialUuidGenerator(), $this->clock);

        $this->userId = UserId::fromString('01900000-0000-7000-8000-000000000001');
    }

    public function testRequestForUnknownEmailIsSilent(): void
    {
        $handler = new RequestPasswordResetHandler($this->users, $this->tokenService, $this->mailer);

        ($handler)(new RequestPasswordResetCommand('missing@example.com'));

        self::assertCount(0, $this->mailer->resets);
        self::assertSame(0, $this->tokens->count());
    }

    public function testRequestForInactiveUserIsSilent(): void
    {
        $this->seedUser(false);
        $handler = new RequestPasswordResetHandler($this->users, $this->tokenService, $this->mailer);

        ($handler)(new RequestPasswordResetCommand(self::EMAIL));

        self::assertCount(0, $this->mailer->resets);
    }

    public function testValidPasswordResetChangesPasswordAndAudits(): void
    {
        $this->seedUser(true);
        $request = new RequestPasswordResetHandler($this->users, $this->tokenService, $this->mailer);
        ($request)(new RequestPasswordResetCommand(self::EMAIL));

        self::assertCount(1, $this->mailer->resets);
        $rawToken = $this->mailer->resets[0]['token'];

        $reset = new ResetPasswordHandler($this->tokenService, $this->users, $this->hasher, $this->audit, $this->transactions, $this->clock);
        ($reset)(new ResetPasswordCommand($rawToken, self::NEW_PASSWORD));

        $user = $this->users->findById($this->userId);
        self::assertNotNull($user);
        self::assertSame('hashed:'.self::NEW_PASSWORD, $user->passwordHash());
        self::assertTrue($this->audit->hasEvent('user.password_reset'));
        self::assertSame(1, $this->transactions->calls);
    }

    public function testWeakPasswordResetFailsInsideTransaction(): void
    {
        $this->seedUser(true);
        $request = new RequestPasswordResetHandler($this->users, $this->tokenService, $this->mailer);
        ($request)(new RequestPasswordResetCommand(self::EMAIL));

        $rawToken = $this->mailer->resets[0]['token'];
        $reset = new ResetPasswordHandler($this->tokenService, $this->users, $this->hasher, $this->audit, $this->transactions, $this->clock);

        try {
            ($reset)(new ResetPasswordCommand($rawToken, 'short'));
            self::fail('Expected a validation exception for the weak password.');
        } catch (ValidationException) {
            self::assertSame(1, $this->transactions->calls);
            self::assertFalse($this->audit->hasEvent('user.password_reset'));
        }
    }

    public function testChangePasswordWithWrongCurrentPasswordIsForbidden(): void
    {
        $this->seedUser(true);
        $handler = new ChangePasswordHandler($this->users, $this->hasher, $this->audit, $this->clock);

        $this->expectException(ForbiddenException::class);

        ($handler)(new ChangePasswordCommand($this->userId->value(), 'wrong-password-value', self::NEW_PASSWORD));
    }

    public function testChangePasswordSucceedsAndAudits(): void
    {
        $this->seedUser(true);
        $handler = new ChangePasswordHandler($this->users, $this->hasher, $this->audit, $this->clock);

        ($handler)(new ChangePasswordCommand($this->userId->value(), self::CURRENT, self::NEW_PASSWORD));

        $user = $this->users->findById($this->userId);
        self::assertNotNull($user);
        self::assertSame('hashed:'.self::NEW_PASSWORD, $user->passwordHash());
        self::assertTrue($this->audit->hasEvent('user.password_changed'));
    }

    private function seedUser(bool $active): void
    {
        $user = User::register(
            $this->userId,
            Email::fromString(self::EMAIL),
            $this->hasher->hash(self::CURRENT),
            $this->clock->now(),
        );

        if ($active) {
            $user->verifyEmail($this->clock->now());
        }

        $this->users->save($user);
    }
}
