<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Identity\Application;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Identity\Application\Security\UserTokenService;
use SovereignStack\Lms\Identity\Domain\Enum\TokenPurpose;
use SovereignStack\Lms\Identity\Domain\Exception\TokenExpiredException;
use SovereignStack\Lms\Identity\Domain\Exception\TokenInvalidException;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Tests\Support\Fakes\FakeTokenGenerator;
use SovereignStack\Lms\Tests\Support\Fakes\FixedClock;
use SovereignStack\Lms\Tests\Support\Fakes\InMemoryUserTokenRepository;
use SovereignStack\Lms\Tests\Support\Fakes\SequentialUuidGenerator;

/**
 * @internal
 *
 * @coversNothing
 */
final class UserTokenServiceTest extends TestCase
{
    private FixedClock $clock;
    private InMemoryUserTokenRepository $tokens;
    private UserTokenService $service;
    private UserId $userId;

    protected function setUp(): void
    {
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-11-14T10:00:00+00:00'));
        $this->tokens = new InMemoryUserTokenRepository();
        $this->service = new UserTokenService($this->tokens, new FakeTokenGenerator(), new SequentialUuidGenerator(), $this->clock);
        $this->userId = UserId::fromString('01900000-0000-7000-8000-000000000001');
    }

    public function testEmailVerificationTokenExpiresAfterTwentyFourHours(): void
    {
        $rawToken = $this->service->issue($this->userId, TokenPurpose::EMAIL_VERIFICATION, $this->clock->now());

        $this->expectException(TokenExpiredException::class);

        $this->service->consume($rawToken, TokenPurpose::EMAIL_VERIFICATION, $this->clock->now()->modify('+25 hours'));
    }

    public function testPasswordResetTokenExpiresAfterOneHour(): void
    {
        $rawToken = $this->service->issue($this->userId, TokenPurpose::PASSWORD_RESET, $this->clock->now());

        $this->expectException(TokenExpiredException::class);

        $this->service->consume($rawToken, TokenPurpose::PASSWORD_RESET, $this->clock->now()->modify('+61 minutes'));
    }

    public function testTokenIsSingleUse(): void
    {
        $rawToken = $this->service->issue($this->userId, TokenPurpose::PASSWORD_RESET, $this->clock->now());

        $this->service->consume($rawToken, TokenPurpose::PASSWORD_RESET, $this->clock->now());

        $this->expectException(TokenInvalidException::class);

        $this->service->consume($rawToken, TokenPurpose::PASSWORD_RESET, $this->clock->now());
    }

    public function testPurposeMismatchIsRejected(): void
    {
        $rawToken = $this->service->issue($this->userId, TokenPurpose::EMAIL_VERIFICATION, $this->clock->now());

        $this->expectException(TokenInvalidException::class);

        $this->service->consume($rawToken, TokenPurpose::PASSWORD_RESET, $this->clock->now());
    }

    public function testIssuingAgainReplacesPreviousActiveToken(): void
    {
        $first = $this->service->issue($this->userId, TokenPurpose::PASSWORD_RESET, $this->clock->now());
        $this->service->issue($this->userId, TokenPurpose::PASSWORD_RESET, $this->clock->now());

        $this->expectException(TokenInvalidException::class);

        $this->service->consume($first, TokenPurpose::PASSWORD_RESET, $this->clock->now());
    }
}
