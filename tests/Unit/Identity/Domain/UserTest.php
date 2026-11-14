<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Identity\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Identity\Domain\Entity\User;
use SovereignStack\Lms\Identity\Domain\Enum\UserStatus;
use SovereignStack\Lms\Identity\Domain\Exception\InvalidAccountStateException;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;

/**
 * @internal
 *
 * @coversNothing
 */
final class UserTest extends TestCase
{
    private const USER_ID = '01900000-0000-7000-8000-000000000010';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-14T10:00:00+00:00');
    }

    public function testRegisterCreatesPendingUser(): void
    {
        $user = $this->registered();

        self::assertSame(self::USER_ID, $user->id()->value());
        self::assertSame(UserStatus::PENDING_VERIFICATION, $user->status());
        self::assertNull($user->emailVerifiedAt());
        self::assertSame(0, $user->failedLoginCount());
        self::assertNull($user->lockedUntil());
        self::assertNull($user->lastLoginAt());
        self::assertEquals($this->now, $user->createdAt());
        self::assertEquals($this->now, $user->updatedAt());
        self::assertFalse($user->canAuthenticate($this->now));
    }

    public function testVerifyEmailActivatesAndIsIdempotent(): void
    {
        $user = $this->registered();
        $verifiedAt = $this->now->modify('+1 hour');

        $user->verifyEmail($verifiedAt);

        self::assertSame(UserStatus::ACTIVE, $user->status());
        self::assertEquals($verifiedAt, $user->emailVerifiedAt());
        self::assertEquals($verifiedAt, $user->updatedAt());
        self::assertTrue($user->canAuthenticate($verifiedAt));

        $user->verifyEmail($this->now->modify('+2 hours'));

        self::assertSame(UserStatus::ACTIVE, $user->status());
        self::assertEquals($verifiedAt, $user->emailVerifiedAt());
        self::assertEquals($verifiedAt, $user->updatedAt());
    }

    public function testVerifyEmailRejectsSuspendedAccount(): void
    {
        $user = $this->registered();
        $user->suspend($this->now);

        $this->expectException(InvalidAccountStateException::class);

        $user->verifyEmail($this->now);
    }

    public function testFailedLoginsLockAccountAtThresholdWithoutTouchingUpdatedAt(): void
    {
        $user = $this->active();

        for ($i = 0; $i < 4; ++$i) {
            $user->recordFailedLogin($this->now->modify('+'.$i.' minutes'));
        }

        self::assertSame(4, $user->failedLoginCount());
        self::assertNull($user->lockedUntil());
        self::assertFalse($user->isLocked($this->now->modify('+4 minutes')));

        $user->recordFailedLogin($this->now->modify('+5 minutes'));

        self::assertSame(5, $user->failedLoginCount());
        self::assertEquals($this->now->modify('+20 minutes'), $user->lockedUntil());
        self::assertTrue($user->isLocked($this->now->modify('+6 minutes')));
        self::assertFalse($user->canAuthenticate($this->now->modify('+6 minutes')));
        self::assertEquals($this->now, $user->updatedAt());
    }

    public function testSuccessfulLoginResetsFailuresAndLock(): void
    {
        $user = $this->active();

        for ($i = 0; $i < 5; ++$i) {
            $user->recordFailedLogin($this->now);
        }

        $loginAt = $this->now->modify('+30 minutes');
        $user->recordSuccessfulLogin($loginAt);

        self::assertSame(0, $user->failedLoginCount());
        self::assertNull($user->lockedUntil());
        self::assertEquals($loginAt, $user->lastLoginAt());
        self::assertFalse($user->isLocked($loginAt));
        self::assertTrue($user->canAuthenticate($loginAt));
        self::assertEquals($this->now, $user->updatedAt());
    }

    public function testLoginRecordingDoesNotChangeUpdatedAt(): void
    {
        $user = $this->registered();
        $createdUpdatedAt = $user->updatedAt();

        $user->recordSuccessfulLogin($this->now->modify('+5 minutes'));
        $user->recordFailedLogin($this->now->modify('+6 minutes'));

        self::assertEquals($createdUpdatedAt, $user->updatedAt());
    }

    public function testSuspendAndDeactivatePreventAuthentication(): void
    {
        $user = $this->active();

        $user->suspend($this->now->modify('+1 minute'));

        self::assertSame(UserStatus::SUSPENDED, $user->status());
        self::assertFalse($user->canAuthenticate($this->now->modify('+1 minute')));
        self::assertEquals($this->now->modify('+1 minute'), $user->updatedAt());

        $user->deactivate($this->now->modify('+2 minutes'));

        self::assertSame(UserStatus::DEACTIVATED, $user->status());
        self::assertFalse($user->canAuthenticate($this->now->modify('+2 minutes')));
    }

    public function testChangePasswordUpdatesHashAndTimestamp(): void
    {
        $user = $this->active();
        $changedAt = $this->now->modify('+1 hour');

        $user->changePassword('new-hash', $changedAt);

        self::assertSame('new-hash', $user->passwordHash());
        self::assertEquals($changedAt, $user->updatedAt());
    }

    public function testMarkEmailVerifiedOnlySetsTimestampOnce(): void
    {
        $user = $this->registered();
        $first = $this->now->modify('+1 hour');

        $user->markEmailVerified($first);
        $user->markEmailVerified($this->now->modify('+2 hours'));

        self::assertEquals($first, $user->emailVerifiedAt());
        self::assertSame(UserStatus::PENDING_VERIFICATION, $user->status());
    }

    private function registered(): User
    {
        return User::register(
            UserId::fromString(self::USER_ID),
            Email::fromString('learner@example.com'),
            'hashed-password',
            $this->now,
        );
    }

    private function active(): User
    {
        $user = $this->registered();
        $user->verifyEmail($this->now);

        return $user;
    }
}
