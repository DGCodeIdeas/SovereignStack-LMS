<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use SovereignStack\Lms\Identity\Domain\Entity\UserToken;
use SovereignStack\Lms\Identity\Domain\Enum\TokenPurpose;
use SovereignStack\Lms\Identity\Domain\Repository\UserTokenRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DatabaseValue;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DateTimeCodec;

final readonly class DBALUserTokenRepository implements UserTokenRepositoryInterface
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {}

    public function replaceActiveForPurpose(UserId $userId, TokenPurpose $purpose, UserToken $token): void
    {
        $now = DateTimeCodec::toDatabase($this->clock->now());

        $this->connection->transactional(function (Connection $connection) use ($userId, $purpose, $token, $now): void {
            $connection->executeStatement(
                'UPDATE user_tokens SET consumed_at = :now WHERE user_id = :user_id AND purpose = :purpose AND consumed_at IS NULL',
                [
                    'now' => $now,
                    'user_id' => $userId->value(),
                    'purpose' => $purpose->value,
                ],
            );

            $connection->insert('user_tokens', [
                'id' => $token->id(),
                'user_id' => $token->userId()->value(),
                'purpose' => $token->purpose()->value,
                'token_hash' => $token->tokenHash(),
                'expires_at' => DateTimeCodec::toDatabase($token->expiresAt()),
                'consumed_at' => DateTimeCodec::toDatabaseNullable($token->consumedAt()),
                'created_at' => DateTimeCodec::toDatabase($token->createdAt()),
            ]);
        });
    }

    public function findByHash(string $tokenHash): ?UserToken
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM user_tokens WHERE token_hash = :token_hash',
            ['token_hash' => $tokenHash],
        );

        if (false === $row) {
            return null;
        }

        return UserToken::restore(
            DatabaseValue::string($row, 'id'),
            UserId::fromString(DatabaseValue::string($row, 'user_id')),
            TokenPurpose::from(DatabaseValue::string($row, 'purpose')),
            DatabaseValue::string($row, 'token_hash'),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'expires_at')),
            DateTimeCodec::fromDatabaseNullable(DatabaseValue::nullableString($row, 'consumed_at')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'created_at')),
        );
    }

    public function markConsumed(UserToken $token): void
    {
        $this->connection->update(
            'user_tokens',
            ['consumed_at' => DateTimeCodec::toDatabaseNullable($token->consumedAt())],
            ['id' => $token->id()],
        );
    }

    public function deleteExpired(\DateTimeImmutable $before): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM user_tokens WHERE expires_at < :before AND consumed_at IS NULL',
            ['before' => DateTimeCodec::toDatabase($before)],
        );
    }
}
