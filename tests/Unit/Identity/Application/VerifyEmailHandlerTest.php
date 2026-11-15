<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Identity\Application;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Identity\Application\Command\VerifyEmailCommand;
use SovereignStack\Lms\Identity\Application\Handler\VerifyEmailHandler;
use SovereignStack\Lms\Identity\Application\Security\UserTokenService;
use SovereignStack\Lms\Identity\Domain\Entity\User;
use SovereignStack\Lms\Identity\Domain\Enum\TokenPurpose;
use SovereignStack\Lms\Identity\Domain\Enum\UserStatus;
use SovereignStack\Lms\Identity\Domain\Exception\TokenInvalidException;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Tests\Support\Fakes\FakeTokenGenerator;
use SovereignStack\Lms\Tests\Support\Fakes\FakeTransactionManager;
use SovereignStack\Lms\Tests\Support\Fakes\FixedClock;
use SovereignStack\Lms\Tests\Support\Fakes\InMemoryUserRepository;
use SovereignStack\Lms\Tests\Support\Fakes\InMemoryUserTokenRepository;
use SovereignStack\Lms\Tests\Support\Fakes\RecordingAuditLogger;
use SovereignStack\Lms\Tests\Support\Fakes\SequentialUuidGenerator;

/**
 * @internal
 *
 * @coversNothing
 */
final class VerifyEmailHandlerTest extends TestCase
{
    private FixedClock $clock;
    private InMemoryUserRepository $users;
    private InMemoryUserTokenRepository $tokens;
    private UserTokenService $tokenService;
    private RecordingAuditLogger $audit;
    private FakeTransactionManager $transactions;
    private UserId $userId;

    protected function setUp(): void
    {
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-11-14T10:00:00+00:00'));
        $this->users = new InMemoryUserRepository();
        $this->tokens = new InMemoryUserTokenRepository();
        $this->audit = new RecordingAuditLogger();
        $this->transactions = new FakeTransactionManager();
        $this->tokenService = new UserTokenService($this->tokens, new FakeTokenGenerator(), new SequentialUuidGenerator(), $this->clock);

        $this->userId = UserId::fromString('01900000-0000-7000-8000-000000000001');
        $this->users->save(User::register(
            $this->userId,
            Email::fromString('jane@example.com'),
            'hashed:correct-horse-battery',
            $this->clock->now(),
        ));
    }

    public function testVerificationActivatesUserAndAudits(): void
    {
        $rawToken = $this->tokenService->issue($this->userId, TokenPurpose::EMAIL_VERIFICATION);
        $handler = new VerifyEmailHandler($this->tokenService, $this->users, $this->audit, $this->transactions, $this->clock);

        ($handler)(new VerifyEmailCommand($rawToken));

        $user = $this->users->findById($this->userId);
        self::assertNotNull($user);
        self::assertSame(UserStatus::ACTIVE, $user->status());
        self::assertNotNull($user->emailVerifiedAt());
        self::assertTrue($this->audit->hasEvent('user.email_verified'));
        self::assertSame(1, $this->transactions->calls);
    }

    public function testInvalidTokenIsRejected(): void
    {
        $handler = new VerifyEmailHandler($this->tokenService, $this->users, $this->audit, $this->transactions, $this->clock);

        $this->expectException(TokenInvalidException::class);

        ($handler)(new VerifyEmailCommand('not-a-real-token'));
    }
}
