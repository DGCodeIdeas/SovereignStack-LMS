<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Identity;

use SovereignStack\Lms\Identity\Domain\Entity\User;
use SovereignStack\Lms\Identity\Domain\Enum\UserStatus;
use SovereignStack\Lms\Identity\Domain\Exception\EmailAlreadyRegisteredException;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Identity\Infrastructure\Persistence\DBALUserRepository;
use SovereignStack\Lms\Tests\Integration\DatabaseIntegrationTestCase;
use SovereignStack\Lms\Tests\Integration\IdentityOrganisationFixtures;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class UserRepositoryTest extends DatabaseIntegrationTestCase
{
    use IdentityOrganisationFixtures;

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncate('user_tokens', 'user_profiles', 'user_roles', 'organisation_memberships', 'users', 'organisations');
        $this->now = new \DateTimeImmutable('2026-11-14T10:00:00+00:00');
    }

    public function testInsertAndFindByIdAndEmail(): void
    {
        $repository = $this->repository();
        $user = $this->user('Learner@Example.com');

        $repository->save($user);

        $byId = $repository->findById($user->id());
        self::assertNotNull($byId);
        self::assertSame($user->id()->value(), $byId->id()->value());
        self::assertSame('learner@example.com', $byId->email()->value());
        self::assertSame(UserStatus::PENDING_VERIFICATION, $byId->status());
        self::assertEquals($this->now, $byId->createdAt());

        $byEmail = $repository->findByEmail(Email::fromString('learner@example.com'));
        self::assertNotNull($byEmail);
        self::assertSame($user->id()->value(), $byEmail->id()->value());

        self::assertTrue($repository->emailExists(Email::fromString('learner@example.com')));
        self::assertFalse($repository->emailExists(Email::fromString('missing@example.com')));
        self::assertNull($repository->findByEmail(Email::fromString('missing@example.com')));
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $repository = $this->repository();
        $repository->save($this->user('duplicate@example.com', 'duplicate@example.com'));

        $this->expectException(EmailAlreadyRegisteredException::class);

        $repository->save($this->user('duplicate@example.com', 'duplicate@example.com'));
    }

    public function testLoginStatePersistsWithoutTouchingUpdatedAt(): void
    {
        $repository = $this->repository();
        $user = $this->user('login@example.com');
        $user->verifyEmail($this->now);
        $repository->save($user);

        for ($i = 0; $i < 5; ++$i) {
            $user->recordFailedLogin($this->now->modify('+'.$i.' minutes'));
        }

        $repository->updateLoginState($user);

        $loaded = $repository->findById($user->id());
        self::assertNotNull($loaded);
        self::assertSame(5, $loaded->failedLoginCount());
        self::assertNotNull($loaded->lockedUntil());
        self::assertTrue($loaded->isLocked($this->now->modify('+10 minutes')));
        self::assertEquals($this->now, $loaded->updatedAt());

        $user->recordSuccessfulLogin($this->now->modify('+1 hour'));
        $repository->updateLoginState($user);

        $reloaded = $repository->findById($user->id());
        self::assertNotNull($reloaded);
        self::assertSame(0, $reloaded->failedLoginCount());
        self::assertNull($reloaded->lockedUntil());
        self::assertNotNull($reloaded->lastLoginAt());
    }

    public function testSaveUpdatesMutableAccountState(): void
    {
        $repository = $this->repository();
        $user = $this->user('updated@example.com');
        $repository->save($user);

        $user->verifyEmail($this->now->modify('+1 minute'));
        $user->changePassword('changed-hash', $this->now->modify('+2 minutes'));
        $repository->save($user);

        $loaded = $repository->findById($user->id());
        self::assertNotNull($loaded);
        self::assertSame(UserStatus::ACTIVE, $loaded->status());
        self::assertSame('changed-hash', $loaded->passwordHash());
        self::assertNotNull($loaded->emailVerifiedAt());
        self::assertEquals($this->now->modify('+2 minutes'), $loaded->updatedAt());
    }

    public function testSearchMatchesEmailAndDisplayNameOrderedNewestFirst(): void
    {
        $repository = $this->repository();
        $first = $this->user('alpha@example.com', null, $this->now->modify('-2 minutes'));
        $second = $this->user('beta@example.com', null, $this->now->modify('-1 minute'));
        $third = $this->user('gamma@example.com', null, $this->now);

        $repository->save($first);
        $repository->save($second);
        $repository->save($third);
        $this->insertProfile($first->id()->value(), 'Zulu Person');
        $this->insertProfile($third->id()->value(), 'Alpha Person');

        $rows = $repository->search('example.com', 10, 0);

        self::assertCount(3, $rows);
        self::assertSame('gamma@example.com', $rows[0]['email']);
        self::assertSame('Alpha Person', $rows[0]['displayName']);
        self::assertSame(UserStatus::PENDING_VERIFICATION->value, $rows[0]['status']);
        self::assertSame('beta@example.com', $rows[1]['email']);
        self::assertSame('alpha@example.com', $rows[2]['email']);
        self::assertSame('Zulu Person', $rows[2]['displayName']);
        self::assertNull($rows[2]['lastLoginAt']);

        $byDisplayName = $repository->search('zulu', 10, 0);
        self::assertCount(1, $byDisplayName);
        self::assertSame('alpha@example.com', $byDisplayName[0]['email']);
        self::assertSame(1, $repository->countSearch('zulu'));
        self::assertSame(3, $repository->countSearch('example.com'));
    }

    public function testSearchAppliesLimitAndOffset(): void
    {
        $repository = $this->repository();

        $repository->save($this->user('one@example.com', null, $this->now->modify('-2 minutes')));
        $repository->save($this->user('two@example.com', null, $this->now->modify('-1 minute')));
        $repository->save($this->user('three@example.com', null, $this->now));

        $rows = $repository->search('example.com', 2, 0);
        self::assertCount(2, $rows);
        self::assertSame('three@example.com', $rows[0]['email']);
        self::assertSame('two@example.com', $rows[1]['email']);

        $secondPage = $repository->search('example.com', 2, 2);
        self::assertCount(1, $secondPage);
        self::assertSame('one@example.com', $secondPage[0]['email']);

        self::assertCount(3, $repository->search('', 1000, 0));
    }

    public function testSearchBindsTheQueryInsteadOfInterpolatingIt(): void
    {
        $repository = $this->repository();
        $repository->save($this->user('safe@example.com'));

        self::assertSame([], $repository->search("' OR 1=1 --", 10, 0));
        self::assertSame(0, $repository->countSearch("' OR 1=1 --"));
    }

    private function insertProfile(string $userId, string $displayName): void
    {
        $this->connection()->insert('user_profiles', [
            'id' => Uuid::v7()->toRfc4122(),
            'user_id' => $userId,
            'display_name' => $displayName,
            'preferred_tz' => 'UTC',
            'metadata' => '{}',
            'created_at' => $this->now->format('Y-m-d H:i:s.u'),
            'updated_at' => $this->now->format('Y-m-d H:i:s.u'),
        ]);
    }

    private function repository(): DBALUserRepository
    {
        return new DBALUserRepository($this->connection());
    }

    private function user(
        string $email,
        ?string $uniqueEmail = null,
        ?\DateTimeImmutable $createdAt = null,
    ): User {
        return User::register(
            UserId::fromString(Uuid::v7()->toRfc4122()),
            Email::fromString($uniqueEmail ?? $email),
            'hashed-password',
            $createdAt ?? $this->now,
        );
    }
}
