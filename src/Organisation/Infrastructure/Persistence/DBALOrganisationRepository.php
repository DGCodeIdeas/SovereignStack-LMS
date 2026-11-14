<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use SovereignStack\Lms\Organisation\Domain\Entity\Organisation;
use SovereignStack\Lms\Organisation\Domain\Enum\OrganisationStatus;
use SovereignStack\Lms\Organisation\Domain\Repository\OrganisationRepositoryInterface;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdInterface;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DatabaseValue;
use SovereignStack\Lms\Shared\Infrastructure\Persistence\DateTimeCodec;

final readonly class DBALOrganisationRepository implements OrganisationRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function save(Organisation $organisation): void
    {
        $data = [
            'name' => $organisation->name(),
            'slug' => $organisation->slug(),
            'status' => $organisation->status()->value,
            'timezone' => $organisation->timezone(),
            'settings' => DatabaseValue::encodeJson($organisation->settings()),
            'updated_at' => DateTimeCodec::toDatabase($organisation->updatedAt()),
        ];

        $exists = false !== $this->connection->fetchOne(
            'SELECT 1 FROM organisations WHERE id = :id',
            ['id' => $organisation->id()->value()],
        );

        if ($exists) {
            $this->connection->update('organisations', $data, ['id' => $organisation->id()->value()]);

            return;
        }

        $this->connection->insert('organisations', $data + [
            'id' => $organisation->id()->value(),
            'created_at' => DateTimeCodec::toDatabase($organisation->createdAt()),
        ]);
    }

    public function findById(OrganisationId $id): ?Organisation
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM organisations WHERE id = :id',
            ['id' => $id->value()],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function findBySlug(string $slug): ?Organisation
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM organisations WHERE slug = :slug',
            ['slug' => $slug],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function listForUser(UserIdInterface $userId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT o.* FROM organisations o'
            .' INNER JOIN organisation_memberships m ON m.organisation_id = o.id'
            .' WHERE m.user_id = :user_id AND m.status = :status'
            .' ORDER BY o.name',
            ['user_id' => $userId->value(), 'status' => 'active'],
        );

        return array_map(fn (array $row): Organisation => $this->hydrate($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Organisation
    {
        return Organisation::restore(
            OrganisationId::fromString(DatabaseValue::string($row, 'id')),
            DatabaseValue::string($row, 'name'),
            DatabaseValue::string($row, 'slug'),
            OrganisationStatus::from(DatabaseValue::string($row, 'status')),
            DatabaseValue::string($row, 'timezone'),
            DatabaseValue::decodeJson(DatabaseValue::nullableString($row, 'settings')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'created_at')),
            DateTimeCodec::fromDatabase(DatabaseValue::string($row, 'updated_at')),
        );
    }
}
