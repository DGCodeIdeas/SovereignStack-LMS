<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class MigrationTest extends DatabaseIntegrationTestCase
{
    private const VERSION = 'SovereignStack\Lms\Migrations\Version20261114000000';

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncate(
            'organisations',
            'users',
            'courses',
            'course_versions',
            'messenger_messages',
            'outbox_messages',
            'audit_events',
            'messenger_prototype_events',
            'messenger_prototype_failures',
        );
    }

    public function testOutboxPendingIndexIsUnfilteredComposite(): void
    {
        $connection = $this->connection();

        $rows = $connection->fetchAllAssociative("SHOW INDEX FROM outbox_messages WHERE Key_name = 'idx_outbox_pending'");
        self::assertCount(2, $rows);

        usort($rows, static fn (array $a, array $b): int => self::asInt($a['Seq_in_index']) <=> self::asInt($b['Seq_in_index']));

        self::assertSame('status', $rows[0]['Column_name']);
        self::assertSame('available_at', $rows[1]['Column_name']);
        self::assertSame(1, self::asInt($rows[0]['Non_unique']));

        $createRow = $connection->fetchAssociative('SHOW CREATE TABLE outbox_messages');
        self::assertIsArray($createRow);
        $create = $createRow['Create Table'] ?? null;
        self::assertIsString($create);
        $normalized = self::normalizeCreateTable($create);

        self::assertStringContainsString('idx_outbox_pending (status, available_at)', $normalized);
        self::assertStringNotContainsString('idx_outbox_pending (status, available_at) WHERE', $normalized);
    }

    public function testCourseVersionCompositeForeignKeyExists(): void
    {
        $connection = $this->connection();

        $courseVersionsRow = $connection->fetchAssociative('SHOW CREATE TABLE course_versions');
        $coursesRow = $connection->fetchAssociative('SHOW CREATE TABLE courses');
        self::assertIsArray($courseVersionsRow);
        self::assertIsArray($coursesRow);

        $courseVersions = $courseVersionsRow['Create Table'] ?? null;
        $courses = $coursesRow['Create Table'] ?? null;
        self::assertIsString($courseVersions);
        self::assertIsString($courses);

        $normalizedCourseVersions = self::normalizeCreateTable($courseVersions);
        $normalizedCourses = self::normalizeCreateTable($courses);

        self::assertStringContainsString('UNIQUE KEY uq_cv_id_course (id, course_id)', $normalizedCourseVersions);
        self::assertStringContainsString(
            'CONSTRAINT fk_courses_published_version FOREIGN KEY (current_published_version_id, current_published_version_course_id) REFERENCES course_versions (id, course_id)',
            $normalizedCourses,
        );
        self::assertStringContainsString('chk_courses_published_version_ownership', $normalizedCourses);

        $triggerCount = $this->fetchInt(
            "SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = DATABASE() AND trigger_name = 'trg_course_versions_release_published_reference'",
        );
        self::assertSame(1, $triggerCount);
    }

    public function testCrossCourseVersionReferenceIsRejected(): void
    {
        $connection = $this->connection();
        $organisationId = $this->insertOrganisation($connection);
        $userId = $this->insertUser($connection);
        $courseA = $this->insertCourse($connection, $organisationId, $userId, 'course-a-'.bin2hex(random_bytes(8)));
        $courseB = $this->insertCourse($connection, $organisationId, $userId, 'course-b-'.bin2hex(random_bytes(8)));
        $versionA = $this->insertVersion($connection, $courseA, $userId, 1);

        $thrown = null;

        try {
            $connection->executeStatement(
                'UPDATE courses SET current_published_version_id = :versionId, current_published_version_course_id = :courseId WHERE id = :id',
                ['versionId' => $versionA, 'courseId' => $courseB, 'id' => $courseB],
            );
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        self::assertInstanceOf(ForeignKeyConstraintViolationException::class, $thrown);

        $checkViolation = null;

        try {
            $connection->executeStatement(
                'UPDATE courses SET current_published_version_id = :versionId, current_published_version_course_id = :courseId WHERE id = :id',
                ['versionId' => $versionA, 'courseId' => $courseA, 'id' => $courseB],
            );
        } catch (\Throwable $e) {
            $checkViolation = $e;
        }

        self::assertInstanceOf(Exception::class, $checkViolation);

        $connection->executeStatement(
            'UPDATE courses SET current_published_version_id = :versionId, current_published_version_course_id = :courseId WHERE id = :id',
            ['versionId' => $versionA, 'courseId' => $courseA, 'id' => $courseA],
        );

        $row = $connection->fetchAssociative(
            'SELECT current_published_version_id, current_published_version_course_id FROM courses WHERE id = :id',
            ['id' => $courseA],
        );

        self::assertIsArray($row);
        self::assertSame($versionA, $row['current_published_version_id']);
        self::assertSame($courseA, $row['current_published_version_course_id']);
    }

    public function testVersionDeletionSetsBothColumnsNull(): void
    {
        $connection = $this->connection();
        $organisationId = $this->insertOrganisation($connection);
        $userId = $this->insertUser($connection);
        $courseA = $this->insertCourse($connection, $organisationId, $userId, 'course-a-'.bin2hex(random_bytes(8)));
        $versionA = $this->insertVersion($connection, $courseA, $userId, 1);

        $connection->executeStatement(
            'UPDATE courses SET current_published_version_id = :versionId, current_published_version_course_id = :courseId WHERE id = :id',
            ['versionId' => $versionA, 'courseId' => $courseA, 'id' => $courseA],
        );
        $connection->executeStatement('DELETE FROM course_versions WHERE id = :id', ['id' => $versionA]);

        $row = $connection->fetchAssociative(
            'SELECT current_published_version_id, current_published_version_course_id FROM courses WHERE id = :id',
            ['id' => $courseA],
        );

        self::assertIsArray($row);
        self::assertNull($row['current_published_version_id']);
        self::assertNull($row['current_published_version_course_id']);
    }

    public function testUuidColumnAndDatetimePrecision(): void
    {
        $connection = $this->connection();

        $idLength = $this->fetchInt(
            "SELECT character_maximum_length FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'courses' AND column_name = 'id'",
        );
        $precision = $this->fetchInt(
            "SELECT datetime_precision FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'courses' AND column_name = 'created_at'",
        );

        self::assertSame(36, $idLength);
        self::assertSame(6, $precision);
    }

    public function testMessengerTableSchema(): void
    {
        $connection = $this->connection();

        $columns = [];
        foreach ($connection->fetchAllAssociative('SHOW COLUMNS FROM messenger_messages') as $column) {
            self::assertIsArray($column);
            $name = $column['Field'] ?? null;
            if (\is_string($name)) {
                $columns[$name] = $column;
            }
        }

        self::assertArrayHasKey('id', $columns);
        self::assertArrayHasKey('queue_name', $columns);
        self::assertArrayHasKey('created_at', $columns);
        self::assertArrayHasKey('available_at', $columns);
        self::assertArrayHasKey('delivered_at', $columns);

        self::assertStringStartsWith('bigint', strtolower(self::asString($columns['id']['Type'] ?? '')));
        self::assertSame('varchar(190)', strtolower(self::asString($columns['queue_name']['Type'] ?? '')));
        self::assertSame('datetime(6)', strtolower(self::asString($columns['created_at']['Type'] ?? '')));
        self::assertSame('datetime(6)', strtolower(self::asString($columns['available_at']['Type'] ?? '')));
        self::assertSame('datetime(6)', strtolower(self::asString($columns['delivered_at']['Type'] ?? '')));
        self::assertSame('YES', $columns['delivered_at']['Null'] ?? null);
    }

    public function testJsonColumnsAccepted(): void
    {
        $connection = $this->connection();
        $version = $this->fetchString('SELECT VERSION()');
        $isMariaDb = str_contains(strtolower($version), 'mariadb');

        $payload = $this->fetchString(
            "SELECT data_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'outbox_messages' AND column_name = 'payload'",
        );
        $payloadType = strtolower($payload);

        self::assertSame($isMariaDb ? 'longtext' : 'json', $payloadType);

        $id = Uuid::v7()->toRfc4122();
        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
        $connection->insert('outbox_messages', [
            'id' => $id,
            'event_id' => Uuid::v7()->toRfc4122(),
            'event_type' => 'test.event',
            'aggregate_type' => 'TestAggregate',
            'aggregate_id' => Uuid::v7()->toRfc4122(),
            'payload' => '{"source":"phase-1","n":7}',
            'occurred_at' => $timestamp,
            'available_at' => $timestamp,
            'status' => 'pending',
            'created_at' => $timestamp,
        ]);

        self::assertSame(1, $this->fetchInt('SELECT JSON_VALID(payload) FROM outbox_messages WHERE id = :id', ['id' => $id]));
    }

    public function testMigrationVersionRecorded(): void
    {
        $connection = $this->connection();

        $count = $this->fetchInt(
            'SELECT COUNT(*) FROM doctrine_migration_versions WHERE version = :version',
            ['version' => self::VERSION],
        );

        self::assertSame(1, $count);
    }

    private function insertOrganisation(Connection $connection): string
    {
        $id = Uuid::v7()->toRfc4122();
        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

        $connection->insert('organisations', [
            'id' => $id,
            'name' => 'Test Organisation',
            'slug' => 'org-'.bin2hex(random_bytes(8)),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return $id;
    }

    private function insertUser(Connection $connection): string
    {
        $id = Uuid::v7()->toRfc4122();
        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

        $connection->insert('users', [
            'id' => $id,
            'email' => 'user-'.bin2hex(random_bytes(8)).'@example.test',
            'password_hash' => 'not-a-real-hash',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return $id;
    }

    private function insertCourse(Connection $connection, string $organisationId, string $userId, string $slug): string
    {
        $id = Uuid::v7()->toRfc4122();
        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

        $connection->insert('courses', [
            'id' => $id,
            'organisation_id' => $organisationId,
            'owner_user_id' => $userId,
            'slug' => $slug,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return $id;
    }

    private function insertVersion(Connection $connection, string $courseId, string $userId, int $versionNumber): string
    {
        $id = Uuid::v7()->toRfc4122();
        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

        $connection->insert('course_versions', [
            'id' => $id,
            'course_id' => $courseId,
            'version_number' => $versionNumber,
            'title' => 'Version '.$versionNumber,
            'created_by' => $userId,
            'created_at' => $timestamp,
        ]);

        return $id;
    }

    private static function normalizeCreateTable(string $sql): string
    {
        $sql = str_replace('`', '', $sql);
        $sql = str_replace(',', ', ', $sql);

        return trim((string) preg_replace('/\s+/', ' ', $sql));
    }
}
