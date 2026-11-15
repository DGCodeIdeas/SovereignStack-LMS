<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

trait IdentityOrganisationFixtures
{
    abstract protected function connection(): Connection;

    protected function insertUser(string $email, string $status = 'active'): string
    {
        $id = Uuid::v7()->toRfc4122();
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

        $this->connection()->insert('users', [
            'id' => $id,
            'email' => $email,
            'password_hash' => 'hashed-password',
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    protected function insertOrganisation(string $slug, string $name = 'Test Organisation'): string
    {
        $id = Uuid::v7()->toRfc4122();
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

        $this->connection()->insert('organisations', [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'timezone' => 'UTC',
            'settings' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function rowString(array $row, string $column): string
    {
        $value = $row[$column] ?? null;

        if (!\is_string($value)) {
            self::fail(sprintf('Expected "%s" to be a string, got %s.', $column, get_debug_type($value)));
        }

        return $value;
    }
}
