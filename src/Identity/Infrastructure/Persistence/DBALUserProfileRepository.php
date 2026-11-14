<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use SovereignStack\Lms\Identity\Domain\Entity\UserProfile;
use SovereignStack\Lms\Identity\Domain\Repository\UserProfileRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DatabaseValue;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DateTimeCodec;

final readonly class DBALUserProfileRepository implements UserProfileRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function save(UserProfile $profile): void
    {
        $data = [
            'user_id' => $profile->userId()->value(),
            'display_name' => $profile->displayName(),
            'preferred_tz' => $profile->preferredTz(),
            'metadata' => DatabaseValue::encodeJson($profile->metadata()),
            'updated_at' => DateTimeCodec::toDatabase($profile->updatedAt()),
        ];

        $exists = false !== $this->connection->fetchOne(
            'SELECT 1 FROM user_profiles WHERE id = :id',
            ['id' => $profile->id()],
        );

        if ($exists) {
            $this->connection->update('user_profiles', $data, ['id' => $profile->id()]);

            return;
        }

        $this->connection->insert('user_profiles', $data + [
            'id' => $profile->id(),
            'created_at' => DateTimeCodec::toDatabase($profile->createdAt()),
        ]);
    }

    public function findByUserId(UserId $userId): ?UserProfile
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM user_profiles WHERE user_id = :user_id',
            ['user_id' => $userId->value()],
        );

        if (false === $row) {
            return null;
        }

        return UserProfile::restore(
            DatabaseValue::string($row, 'id'),
            UserId::fromString(DatabaseValue::string($row, 'user_id')),
            DatabaseValue::string($row, 'display_name'),
            DatabaseValue::string($row, 'preferred_tz'),
            DatabaseValue::decodeJson(DatabaseValue::nullableString($row, 'metadata')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'created_at')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'updated_at')),
        );
    }
}
