<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use SovereignStack\Lms\Organisation\Domain\Entity\Membership;
use SovereignStack\Lms\Organisation\Domain\Enum\MembershipStatus;
use SovereignStack\Lms\Organisation\Domain\Repository\MembershipRepositoryInterface;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdInterface;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DatabaseValue;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DateTimeCodec;

final readonly class DBALMembershipRepository implements MembershipRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function save(Membership $membership): void
    {
        $data = [
            'organisation_id' => $membership->organisationId()->value(),
            'user_id' => $membership->userId(),
            'status' => $membership->status()->value,
            'joined_at' => DateTimeCodec::toDatabaseNullable($membership->joinedAt()),
            'ended_at' => DateTimeCodec::toDatabaseNullable($membership->endedAt()),
        ];

        $exists = false !== $this->connection->fetchOne(
            'SELECT 1 FROM organisation_memberships WHERE id = :id',
            ['id' => $membership->id()],
        );

        if ($exists) {
            $this->connection->update('organisation_memberships', $data, ['id' => $membership->id()]);

            return;
        }

        $this->connection->insert('organisation_memberships', $data + [
            'id' => $membership->id(),
            'created_at' => DateTimeCodec::toDatabase($membership->createdAt()),
        ]);
    }

    public function findByOrganisationAndUser(OrganisationId $organisationId, UserIdInterface $userId): ?Membership
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM organisation_memberships WHERE organisation_id = :organisation_id AND user_id = :user_id',
            ['organisation_id' => $organisationId->value(), 'user_id' => $userId->value()],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function listForUser(UserIdInterface $userId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM organisation_memberships WHERE user_id = :user_id ORDER BY created_at',
            ['user_id' => $userId->value()],
        );

        return array_map(fn (array $row): Membership => $this->hydrate($row), $rows);
    }

    public function listForOrganisation(OrganisationId $organisationId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM organisation_memberships WHERE organisation_id = :organisation_id ORDER BY created_at',
            ['organisation_id' => $organisationId->value()],
        );

        return array_map(fn (array $row): Membership => $this->hydrate($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Membership
    {
        return Membership::restore(
            DatabaseValue::string($row, 'id'),
            OrganisationId::fromString(DatabaseValue::string($row, 'organisation_id')),
            DatabaseValue::string($row, 'user_id'),
            MembershipStatus::from(DatabaseValue::string($row, 'status')),
            DateTimeCodec::fromDatabaseNullable(DatabaseValue::nullableString($row, 'joined_at')),
            DateTimeCodec::fromDatabaseNullable(DatabaseValue::nullableString($row, 'ended_at')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'created_at')),
        );
    }
}
