<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Identity\Infrastructure\Security;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Identity\Domain\Entity\User;
use SovereignStack\Lms\Identity\Domain\Enum\UserStatus;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Identity\Infrastructure\Security\DbalUserProvider;
use SovereignStack\Lms\Identity\Infrastructure\Security\SecurityUser;
use SovereignStack\Lms\Tests\Support\Fakes\FixedClock;
use SovereignStack\Lms\Tests\Support\Fakes\InMemoryRoleRepository;
use SovereignStack\Lms\Tests\Support\Fakes\InMemoryUserRepository;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/**
 * @internal
 *
 * @coversNothing
 */
final class DbalUserProviderTest extends TestCase
{
    private const USER_ID = '01900000-0000-7000-8000-000000000001';

    private FixedClock $clock;
    private InMemoryUserRepository $users;
    private DbalUserProvider $provider;
    private UserId $userId;

    protected function setUp(): void
    {
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-11-17T10:00:00+00:00'));
        $this->users = new InMemoryUserRepository();
        $this->provider = new DbalUserProvider($this->users, new InMemoryRoleRepository(), $this->clock);
        $this->userId = UserId::fromString(self::USER_ID);
    }

    public function testRefreshReturnsSecurityUserForActiveAccount(): void
    {
        $user = $this->activeUser();
        $refreshed = $this->provider->refreshUser(new SecurityUser($user, [], []));

        self::assertInstanceOf(SecurityUser::class, $refreshed);
        self::assertSame(UserStatus::ACTIVE, $refreshed->getDomainUser()->status());
    }

    public function testRefreshReturnsSecurityUserForSuspendedAccountWithoutThrowing(): void
    {
        $user = $this->activeUser();
        $user->suspend($this->clock->now());
        $this->users->save($user);

        $refreshed = $this->provider->refreshUser(new SecurityUser($user, [], []));

        self::assertInstanceOf(SecurityUser::class, $refreshed);
        self::assertSame(UserStatus::SUSPENDED, $refreshed->getDomainUser()->status());
    }

    public function testRefreshReturnsSecurityUserForDeactivatedAccountWithoutThrowing(): void
    {
        $user = $this->activeUser();
        $user->deactivate($this->clock->now());
        $this->users->save($user);

        $refreshed = $this->provider->refreshUser(new SecurityUser($user, [], []));

        self::assertInstanceOf(SecurityUser::class, $refreshed);
        self::assertSame(UserStatus::DEACTIVATED, $refreshed->getDomainUser()->status());
    }

    public function testRefreshReturnsSecurityUserForLockedAccountWithoutThrowing(): void
    {
        $user = $this->activeUser();

        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $user->recordFailedLogin($this->clock->now());
        }

        $this->users->save($user);

        $refreshed = $this->provider->refreshUser(new SecurityUser($user, [], []));

        self::assertInstanceOf(SecurityUser::class, $refreshed);
        self::assertTrue($refreshed->getDomainUser()->isLocked($this->clock->now()));
    }

    public function testRefreshThrowsUserNotFoundForDeletedAccount(): void
    {
        $user = $this->activeUser();
        $securityUser = new SecurityUser($user, [], []);
        $this->users = new InMemoryUserRepository();
        $provider = new DbalUserProvider($this->users, new InMemoryRoleRepository(), $this->clock);

        $this->expectException(UserNotFoundException::class);

        $provider->refreshUser($securityUser);
    }

    public function testLoginRejectsSuspendedAccount(): void
    {
        $user = $this->activeUser();
        $user->suspend($this->clock->now());
        $this->users->save($user);

        $this->expectException(CustomUserMessageAccountStatusException::class);

        $this->provider->loadUserByIdentifier('active@example.com');
    }

    public function testLoginRejectsDeactivatedAccount(): void
    {
        $user = $this->activeUser();
        $user->deactivate($this->clock->now());
        $this->users->save($user);

        $this->expectException(CustomUserMessageAccountStatusException::class);

        $this->provider->loadUserByIdentifier('active@example.com');
    }

    public function testLoginRejectsPendingAccount(): void
    {
        $user = User::register(
            $this->userId,
            Email::fromString('active@example.com'),
            'hashed-password',
            $this->clock->now(),
        );
        $this->users->save($user);

        $this->expectException(CustomUserMessageAccountStatusException::class);

        $this->provider->loadUserByIdentifier('active@example.com');
    }

    public function testLoginRejectsLockedAccount(): void
    {
        $user = $this->activeUser();

        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $user->recordFailedLogin($this->clock->now());
        }

        $this->users->save($user);

        $this->expectException(CustomUserMessageAccountStatusException::class);

        $this->provider->loadUserByIdentifier('active@example.com');
    }

    private function activeUser(): User
    {
        $user = User::register(
            $this->userId,
            Email::fromString('active@example.com'),
            'hashed-password',
            $this->clock->now(),
        );
        $user->verifyEmail($this->clock->now());
        $this->users->save($user);

        return $user;
    }
}
