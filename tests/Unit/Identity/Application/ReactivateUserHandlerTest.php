<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Identity\Application;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Identity\Application\Command\ReactivateUserCommand;
use SovereignStack\Lms\Identity\Application\Handler\ReactivateUserHandler;
use SovereignStack\Lms\Identity\Domain\Entity\User;
use SovereignStack\Lms\Identity\Domain\Enum\UserStatus;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Tests\Support\Fakes\FakePermissionChecker;
use SovereignStack\Lms\Tests\Support\Fakes\FixedClock;
use SovereignStack\Lms\Tests\Support\Fakes\InMemoryUserRepository;
use SovereignStack\Lms\Tests\Support\Fakes\RecordingAuditLogger;

/**
 * @internal
 *
 * @coversNothing
 */
final class ReactivateUserHandlerTest extends TestCase
{
    private const ACTOR = '01900000-0000-7000-8000-000000000901';
    private const TARGET = '01900000-0000-7000-8000-000000000902';

    public function testReactivatesSuspendedUserAndAudits(): void
    {
        $clock = new FixedClock(new \DateTimeImmutable('2026-11-18T00:00:00+00:00'));
        $users = new InMemoryUserRepository();
        $audit = new RecordingAuditLogger();
        $userId = UserId::fromString(self::TARGET);
        $user = User::register($userId, Email::fromString('reactivate@example.com'), 'hash', $clock->now());
        $user->verifyEmail($clock->now());
        $user->suspend($clock->now());
        $users->save($user);

        $handler = new ReactivateUserHandler(
            new FakePermissionChecker(),
            $users,
            $audit,
            $clock,
        );

        $handler(new ReactivateUserCommand(self::ACTOR, self::TARGET));

        $reloaded = $users->findById($userId);
        self::assertNotNull($reloaded);
        self::assertSame(UserStatus::ACTIVE, $reloaded->status());
        self::assertTrue($audit->hasEvent('user.reactivated'));
    }
}
