<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Identity;

use SovereignStack\Lms\Identity\Domain\Entity\UserToken;
use SovereignStack\Lms\Identity\Domain\Enum\TokenPurpose;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Identity\Infrastructure\Persistence\DBALUserTokenRepository;
use SovereignStack\Lms\Shared\Infrastructure\Clock\SystemClock;
use SovereignStack\Lms\Tests\Integration\DatabaseIntegrationTestCase;
use SovereignStack\Lms\Tests\Integration\IdentityOrganisationFixtures;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class UserTokenRepositoryTest extends DatabaseIntegrationTestCase
{
    use IdentityOrganisationFixtures;

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncate('user_tokens', 'user_profiles', 'user_roles', 'organisation_memberships', 'users', 'organisations');
        $this->now = new \DateTimeImmutable('2026-11-14T10:00:00+00:00');
    }

    public function testReplaceActiveForPurposeConsumesPreviousTokens(): void
    {
        $repository = $this->repository();
        $userId = UserId::fromString($this->insertUser('tokens@example.com'));

        $first = $this->token($userId, 'first');
        $repository->replaceActiveForPurpose($userId, TokenPurpose::EMAIL_VERIFICATION, $first);

        $loadedFirst = $repository->findByHash($first->tokenHash());
        self::assertNotNull($loadedFirst);
        self::assertFalse($loadedFirst->isConsumed());

        $second = $this->token($userId, 'second');
        $repository->replaceActiveForPurpose($userId, TokenPurpose::EMAIL_VERIFICATION, $second);

        $consumedFirst = $repository->findByHash($first->tokenHash());
        self::assertNotNull($consumedFirst);
        self::assertTrue($consumedFirst->isConsumed());

        $loadedSecond = $repository->findByHash($second->tokenHash());
        self::assertNotNull($loadedSecond);
        self::assertFalse($loadedSecond->isConsumed());
        self::assertSame(TokenPurpose::EMAIL_VERIFICATION, $loadedSecond->purpose());
    }

    public function testConsumePersistsConsumedAt(): void
    {
        $repository = $this->repository();
        $userId = UserId::fromString($this->insertUser('consume@example.com'));

        $token = $this->token($userId, 'consume');
        $repository->replaceActiveForPurpose($userId, TokenPurpose::PASSWORD_RESET, $token);

        $token->consume($this->now->modify('+5 minutes'));
        $repository->markConsumed($token);

        $loaded = $repository->findByHash($token->tokenHash());
        self::assertNotNull($loaded);
        self::assertTrue($loaded->isConsumed());
        self::assertEquals($this->now->modify('+5 minutes'), $loaded->consumedAt());
    }

    public function testExpiredTokensAreFoundButReportedExpired(): void
    {
        $repository = $this->repository();
        $userId = UserId::fromString($this->insertUser('expired@example.com'));

        $expired = UserToken::issue(
            Uuid::v7()->toRfc4122(),
            $userId,
            TokenPurpose::PASSWORD_RESET,
            hash('sha256', 'expired-token'),
            $this->now->modify('-1 hour'),
            $this->now->modify('-2 hours'),
        );

        $repository->replaceActiveForPurpose($userId, TokenPurpose::PASSWORD_RESET, $expired);

        $loaded = $repository->findByHash($expired->tokenHash());
        self::assertNotNull($loaded);
        self::assertTrue($loaded->isExpired($this->now));
    }

    public function testDeleteExpiredRemovesUnconsumedExpiredTokens(): void
    {
        $repository = $this->repository();
        $userId = UserId::fromString($this->insertUser('delete-expired@example.com'));

        $expired = UserToken::issue(
            Uuid::v7()->toRfc4122(),
            $userId,
            TokenPurpose::EMAIL_VERIFICATION,
            hash('sha256', 'delete-expired'),
            $this->now->modify('-1 hour'),
            $this->now->modify('-2 hours'),
        );
        $repository->replaceActiveForPurpose($userId, TokenPurpose::EMAIL_VERIFICATION, $expired);

        $deleted = $repository->deleteExpired($this->now);

        self::assertSame(1, $deleted);
        self::assertNull($repository->findByHash($expired->tokenHash()));
    }

    public function testFindByUnknownHashReturnsNull(): void
    {
        self::assertNull($this->repository()->findByHash(hash('sha256', 'unknown')));
    }

    private function repository(): DBALUserTokenRepository
    {
        return new DBALUserTokenRepository($this->connection(), new SystemClock());
    }

    private function token(UserId $userId, string $seed): UserToken
    {
        return UserToken::issue(
            Uuid::v7()->toRfc4122(),
            $userId,
            TokenPurpose::EMAIL_VERIFICATION,
            hash('sha256', $seed),
            $this->now->modify('+1 day'),
            $this->now,
        );
    }
}
