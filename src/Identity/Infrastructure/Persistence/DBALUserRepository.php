<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use SovereignStack\Lms\Identity\Domain\Entity\User;
use SovereignStack\Lms\Identity\Domain\Enum\UserStatus;
use SovereignStack\Lms\Identity\Domain\Exception\EmailAlreadyRegisteredException;
use SovereignStack\Lms\Identity\Domain\Repository\UserRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DatabaseValue;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DateTimeCodec;

final readonly class DBALUserRepository implements UserRepositoryInterface
{
    private const MAX_SEARCH_LIMIT = 100;

    public function __construct(
        private Connection $connection,
    ) {}

    public function save(User $user): void
    {
        $data = [
            'email' => $user->email()->value(),
            'password_hash' => $user->passwordHash(),
            'status' => $user->status()->value,
            'email_verified_at' => DateTimeCodec::toDatabaseNullable($user->emailVerifiedAt()),
            'failed_login_count' => $user->failedLoginCount(),
            'locked_until' => DateTimeCodec::toDatabaseNullable($user->lockedUntil()),
            'last_login_at' => DateTimeCodec::toDatabaseNullable($user->lastLoginAt()),
            'updated_at' => DateTimeCodec::toDatabase($user->updatedAt()),
        ];

        try {
            if ($this->exists($user->id())) {
                $this->connection->update('users', $data, ['id' => $user->id()->value()]);

                return;
            }

            $this->connection->insert('users', $data + [
                'id' => $user->id()->value(),
                'created_at' => DateTimeCodec::toDatabase($user->createdAt()),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            throw EmailAlreadyRegisteredException::forEmail($user->email()->value());
        }
    }

    public function updateLoginState(User $user): void
    {
        $this->connection->update('users', [
            'failed_login_count' => $user->failedLoginCount(),
            'locked_until' => DateTimeCodec::toDatabaseNullable($user->lockedUntil()),
            'last_login_at' => DateTimeCodec::toDatabaseNullable($user->lastLoginAt()),
        ], ['id' => $user->id()->value()]);
    }

    public function findById(UserId $id): ?User
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM users WHERE id = :value',
            ['value' => $id->value()],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function findByEmail(Email $email): ?User
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM users WHERE email = :value',
            ['value' => $email->value()],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function emailExists(Email $email): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM users WHERE email = :email',
            ['email' => $email->value()],
        );
    }

    public function search(string $query, int $limit, int $offset): array
    {
        $term = trim($query);
        $limit = max(1, min(self::MAX_SEARCH_LIMIT, $limit));
        $offset = max(0, $offset);

        $sql = 'SELECT u.id, u.email, u.status, p.display_name, u.created_at, u.last_login_at'
            .' FROM users u'
            .' LEFT JOIN user_profiles p ON p.user_id = u.id';
        $params = [];

        if ('' !== $term) {
            $sql .= ' WHERE (u.email LIKE :like OR p.display_name LIKE :like)';
            $params['like'] = '%'.$term.'%';
        }

        $sql .= ' ORDER BY u.created_at DESC, u.id DESC LIMIT :limit OFFSET :offset';
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $rows = $this->connection->fetchAllAssociative($sql, $params, [
            'limit' => ParameterType::INTEGER,
            'offset' => ParameterType::INTEGER,
        ]);

        return array_map(
            static fn (array $row): array => [
                'id' => DatabaseValue::string($row, 'id'),
                'email' => DatabaseValue::string($row, 'email'),
                'status' => DatabaseValue::string($row, 'status'),
                'displayName' => DatabaseValue::nullableString($row, 'display_name') ?? '',
                'createdAt' => DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'created_at'))->format(\DATE_ATOM),
                'lastLoginAt' => null === DatabaseValue::nullableString($row, 'last_login_at')
                    ? null
                    : DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'last_login_at'))->format(\DATE_ATOM),
            ],
            $rows,
        );
    }

    public function countSearch(string $query): int
    {
        $term = trim($query);

        $sql = 'SELECT COUNT(*) FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id';
        $params = [];

        if ('' !== $term) {
            $sql .= ' WHERE (u.email LIKE :like OR p.display_name LIKE :like)';
            $params['like'] = '%'.$term.'%';
        }

        $count = $this->connection->fetchOne($sql, $params);

        return \is_numeric($count) ? (int) $count : 0;
    }

    private function exists(UserId $id): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM users WHERE id = :id',
            ['id' => $id->value()],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): User
    {
        return User::restore(
            UserId::fromString(DatabaseValue::string($row, 'id')),
            Email::fromString(DatabaseValue::string($row, 'email')),
            DatabaseValue::string($row, 'password_hash'),
            UserStatus::from(DatabaseValue::string($row, 'status')),
            DateTimeCodec::fromDatabaseNullable(DatabaseValue::nullableString($row, 'email_verified_at')),
            DatabaseValue::int($row, 'failed_login_count'),
            DateTimeCodec::fromDatabaseNullable(DatabaseValue::nullableString($row, 'locked_until')),
            DateTimeCodec::fromDatabaseNullable(DatabaseValue::nullableString($row, 'last_login_at')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'created_at')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'updated_at')),
        );
    }
}
