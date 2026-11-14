<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Identity;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use SovereignStack\Lms\Tests\Integration\DatabaseIntegrationTestCase;
use SovereignStack\Lms\Tests\Integration\IdentityOrganisationFixtures;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class IdentitySchemaTest extends DatabaseIntegrationTestCase
{
    use IdentityOrganisationFixtures;

    private const TABLES = [
        'user_profiles',
        'roles',
        'permissions',
        'role_permissions',
        'user_roles',
        'organisation_memberships',
        'user_tokens',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncate(
            'user_tokens',
            'organisation_memberships',
            'user_roles',
            'user_profiles',
            'courses',
            'course_versions',
            'outbox_messages',
            'audit_events',
            'users',
            'organisations',
        );
    }

    public function testIdentityTablesExist(): void
    {
        foreach (self::TABLES as $table) {
            $count = $this->connection()->fetchOne(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
                ['table' => $table],
            );

            self::assertSame(1, self::asInt($count), sprintf('Table "%s" is missing.', $table));
        }
    }

    public function testRolesAndPermissionsAreSeeded(): void
    {
        self::assertSame(4, $this->fetchInt('SELECT COUNT(*) FROM roles'));
        self::assertSame(19, $this->fetchInt('SELECT COUNT(*) FROM permissions'));
    }

    public function testRoleCodesAndNamesMatchSeed(): void
    {
        $rows = $this->connection()->fetchAllAssociative('SELECT code, name FROM roles ORDER BY code');
        $map = [];

        foreach ($rows as $row) {
            $map[$this->rowString($row, 'code')] = $this->rowString($row, 'name');
        }

        self::assertSame([
            'lms:admin' => 'LMS Administrator',
            'lms:instructor' => 'Instructor',
            'lms:learner' => 'Learner',
            'platform:admin' => 'Platform Administrator',
        ], $map);
    }

    public function testRolePermissionMappingsMatchSeed(): void
    {
        $rows = $this->connection()->fetchAllAssociative(
            'SELECT r.code AS role_code, COUNT(*) AS permission_count
             FROM role_permissions rp
             INNER JOIN roles r ON r.id = rp.role_id
             GROUP BY r.code
             ORDER BY r.code'
        );

        $counts = [];

        foreach ($rows as $row) {
            $counts[$this->rowString($row, 'role_code')] = self::asInt($row['permission_count'] ?? null);
        }

        self::assertSame([
            'lms:admin' => 19,
            'lms:instructor' => 9,
            'lms:learner' => 6,
            'platform:admin' => 19,
        ], $counts);
    }

    public function testForeignKeysExist(): void
    {
        $foreignKeys = [
            ['user_profiles', 'fk_profiles_user'],
            ['role_permissions', 'fk_rp_role'],
            ['role_permissions', 'fk_rp_perm'],
            ['user_roles', 'fk_ur_user'],
            ['user_roles', 'fk_ur_role'],
            ['user_roles', 'fk_ur_org'],
            ['user_roles', 'fk_ur_assigned_by'],
            ['organisation_memberships', 'fk_mem_org'],
            ['organisation_memberships', 'fk_mem_user'],
            ['user_tokens', 'fk_user_tokens_user'],
        ];

        foreach ($foreignKeys as [$table, $constraint]) {
            $count = $this->connection()->fetchOne(
                'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table
                   AND CONSTRAINT_NAME = :constraint AND CONSTRAINT_TYPE = :type',
                ['table' => $table, 'constraint' => $constraint, 'type' => 'FOREIGN KEY'],
            );

            self::assertSame(1, self::asInt($count), sprintf('Foreign key "%s" on "%s" is missing.', $constraint, $table));
        }
    }

    public function testUniqueConstraintsExist(): void
    {
        $this->assertUniqueIndex('user_profiles', 'uq_user_profiles_user', ['user_id']);
        $this->assertUniqueIndex('roles', 'uq_roles_code', ['code']);
        $this->assertUniqueIndex('permissions', 'uq_permissions_code', ['code']);
        $this->assertUniqueIndex('organisation_memberships', 'uq_org_user', ['organisation_id', 'user_id']);
        $this->assertUniqueIndex('user_tokens', 'uq_user_tokens_hash', ['token_hash']);
    }

    public function testUserTokenHashMustBeUnique(): void
    {
        $userId = $this->insertUser('token-hash@example.com');
        $hash = hash('sha256', 'duplicate');

        $this->insertToken($userId, $hash);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->insertToken($userId, $hash);
    }

    /**
     * @param list<string> $columns
     */
    private function assertUniqueIndex(string $table, string $index, array $columns): void
    {
        $rows = $this->connection()->fetchAllAssociative(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
               AND INDEX_NAME = :index AND NON_UNIQUE = 0
             ORDER BY SEQ_IN_INDEX',
            ['table' => $table, 'index' => $index],
        );

        $actual = array_map(fn (array $row): string => $this->rowString($row, 'COLUMN_NAME'), $rows);

        self::assertSame($columns, $actual, sprintf('Unique index "%s" on "%s" does not match.', $index, $table));
    }

    private function insertToken(string $userId, string $hash): void
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

        $this->connection()->insert('user_tokens', [
            'id' => Uuid::v7()->toRfc4122(),
            'user_id' => $userId,
            'purpose' => 'email_verification',
            'token_hash' => $hash,
            'expires_at' => $now,
            'created_at' => $now,
        ]);
    }
}
