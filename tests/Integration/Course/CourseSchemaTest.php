<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Course;

use Doctrine\DBAL\Exception;
use SovereignStack\Lms\Tests\Integration\DatabaseIntegrationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class CourseSchemaTest extends DatabaseIntegrationTestCase
{
    use CourseFixtures;

    private const TABLES = [
        'course_modules',
        'lessons',
        'lesson_contents',
        'course_categories',
        'course_category_assignments',
        'course_instructors',
    ];

    private const TRIGGER = 'trg_course_versions_prevent_content_update';

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncateCourseTables();
    }

    public function testContentTablesExist(): void
    {
        foreach (self::TABLES as $table) {
            $count = $this->connection()->fetchOne(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
                ['table' => $table],
            );

            self::assertSame(1, self::asInt($count), sprintf('Table "%s" is missing.', $table));
        }
    }

    public function testUuidAndDatetimeColumnsUseExpectedTypes(): void
    {
        $expected = [
            ['course_modules', 'id', 'char(36)', null],
            ['course_modules', 'course_version_id', 'char(36)', null],
            ['course_modules', 'created_at', 'datetime(6)', 6],
            ['lessons', 'id', 'char(36)', null],
            ['lessons', 'updated_at', 'datetime(6)', 6],
            ['lesson_contents', 'id', 'char(36)', null],
            ['lesson_contents', 'created_at', 'datetime(6)', 6],
            ['course_categories', 'id', 'char(36)', null],
            ['course_categories', 'created_at', 'datetime(6)', 6],
            ['course_instructors', 'effective_until', 'datetime(6)', 6],
        ];

        foreach ($expected as [$table, $column, $type, $precision]) {
            $row = $this->connection()->fetchAssociative(
                'SELECT COLUMN_TYPE, DATETIME_PRECISION FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column',
                ['table' => $table, 'column' => $column],
            );

            self::assertIsArray($row, sprintf('Column "%s.%s" is missing.', $table, $column));
            self::assertSame($type, strtolower($this->rowString($row, 'COLUMN_TYPE')));

            if (null !== $precision) {
                self::assertSame($precision, self::asInt($row['DATETIME_PRECISION'] ?? null));
            }
        }
    }

    public function testRequiredFlagsAndDefaults(): void
    {
        $module = $this->fetchInt(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'course_modules' AND COLUMN_NAME IN ('title', 'position')
             AND IS_NULLABLE = 'NO'",
        );
        self::assertSame(2, $module);

        $default = $this->connection()->fetchOne(
            "SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_modules' AND COLUMN_NAME = 'is_required'",
        );
        self::assertSame('1', $default);
    }

    public function testUniqueIndexesExist(): void
    {
        $this->assertUniqueIndex('course_modules', 'uq_cm_version_position', ['course_version_id', 'position']);
        $this->assertUniqueIndex('lessons', 'uq_lessons_module_position', ['course_module_id', 'position']);
        $this->assertUniqueIndex('lesson_contents', 'uq_lc_lesson', ['lesson_id']);
        $this->assertUniqueIndex('course_categories', 'uq_cat_org_slug', ['organisation_id', 'slug']);
    }

    public function testCompositePrimaryKeyForCategoryAssignments(): void
    {
        $this->assertUniqueIndex('course_category_assignments', 'PRIMARY', ['course_id', 'category_id']);
    }

    public function testForeignKeysExist(): void
    {
        $foreignKeys = [
            ['course_modules', 'fk_cm_version'],
            ['lessons', 'fk_lessons_module'],
            ['lesson_contents', 'fk_lc_lesson'],
            ['course_categories', 'fk_cat_org'],
            ['course_category_assignments', 'fk_cca_course'],
            ['course_category_assignments', 'fk_cca_cat'],
            ['course_instructors', 'fk_instr_course'],
            ['course_instructors', 'fk_instr_user'],
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

    public function testDeletingVersionCascadesModulesLessonsAndContent(): void
    {
        [$moduleId, $lessonId] = $this->seedContent(1);

        $this->connection()->executeStatement(
            'DELETE FROM course_versions WHERE id = (SELECT course_version_id FROM course_modules WHERE id = :id)',
            ['id' => $moduleId],
        );

        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM course_modules WHERE id = :id', ['id' => $moduleId]));
        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM lessons WHERE id = :id', ['id' => $lessonId]));
        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM lesson_contents WHERE lesson_id = :id', ['id' => $lessonId]));
    }

    public function testDeletingCategoryCascadesAssignments(): void
    {
        $organisationId = $this->insertOrganisation('cat-'.bin2hex(random_bytes(4)));
        $userId = $this->insertUser('cat-'.bin2hex(random_bytes(4)).'@example.com');
        $courseId = $this->insertCourseRow($organisationId, $userId, 'cat-'.bin2hex(random_bytes(4)));
        $categoryId = $this->insertCategoryRow($organisationId, 'Programming', 'programming');

        $this->connection()->insert('course_category_assignments', [
            'course_id' => $courseId,
            'category_id' => $categoryId,
        ]);

        $this->connection()->executeStatement('DELETE FROM course_categories WHERE id = :id', ['id' => $categoryId]);

        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM course_category_assignments WHERE category_id = :id', ['id' => $categoryId]));
    }

    public function testImmutabilityTriggerExists(): void
    {
        $count = $this->fetchInt(
            'SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = :name',
            ['name' => self::TRIGGER],
        );

        self::assertSame(1, $count);
    }

    public function testTriggerRejectsContentChangesOnPublishedVersion(): void
    {
        $versionId = $this->seedPublishedVersion();

        foreach (['title', 'description', 'version_number', 'course_id', 'created_by'] as $column) {
            $exception = $this->attemptUpdate($versionId, $column);

            self::assertInstanceOf(Exception::class, $exception, sprintf('Changing "%s" on a published version must fail.', $column));
            self::assertStringContainsString('immutable', strtolower($exception->getMessage()));
        }

        $row = $this->connection()->fetchAssociative(
            'SELECT title, version_number FROM course_versions WHERE id = :id',
            ['id' => $versionId],
        );
        self::assertIsArray($row);
        self::assertSame('Version 1', $this->rowString($row, 'title'));
        self::assertSame(1, self::asInt($row['version_number'] ?? null));
    }

    public function testTriggerRejectsContentChangesOnArchivedVersion(): void
    {
        $versionId = $this->seedPublishedVersion();
        $this->connection()->executeStatement(
            "UPDATE course_versions SET status = 'archived' WHERE id = :id",
            ['id' => $versionId],
        );

        $exception = $this->attemptUpdate($versionId, 'title');

        self::assertInstanceOf(Exception::class, $exception);
    }

    public function testTriggerAllowsArchiveAndPublishedAtUpdates(): void
    {
        $versionId = $this->seedPublishedVersion();

        $this->connection()->executeStatement(
            'UPDATE course_versions SET published_at = :published_at WHERE id = :id',
            ['published_at' => $this->timestamp(), 'id' => $versionId],
        );

        $this->connection()->executeStatement(
            "UPDATE course_versions SET status = 'archived' WHERE id = :id",
            ['id' => $versionId],
        );

        $status = $this->fetchString('SELECT status FROM course_versions WHERE id = :id', ['id' => $versionId]);
        self::assertSame('archived', $status);
    }

    public function testTriggerAllowsDraftContentUpdates(): void
    {
        $organisationId = $this->insertOrganisation('draft-'.bin2hex(random_bytes(4)));
        $userId = $this->insertUser('draft-'.bin2hex(random_bytes(4)).'@example.com');
        $courseId = $this->insertCourseRow($organisationId, $userId, 'draft-'.bin2hex(random_bytes(4)));
        $versionId = $this->insertVersionRow($courseId, $userId, 1, 'draft');

        $this->connection()->executeStatement(
            'UPDATE course_versions SET title = :title WHERE id = :id',
            ['title' => 'Renamed draft', 'id' => $versionId],
        );

        self::assertSame('Renamed draft', $this->fetchString('SELECT title FROM course_versions WHERE id = :id', ['id' => $versionId]));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function seedContent(int $versionNumber): array
    {
        $organisationId = $this->insertOrganisation('content-'.bin2hex(random_bytes(4)));
        $userId = $this->insertUser('content-'.bin2hex(random_bytes(4)).'@example.com');
        $courseId = $this->insertCourseRow($organisationId, $userId, 'content-'.bin2hex(random_bytes(4)));
        $versionId = $this->insertVersionRow($courseId, $userId, $versionNumber);
        $moduleId = $this->insertModuleRow($versionId, 'Module', 0);
        $lessonId = $this->insertLessonRow($moduleId, 'Lesson', 0);
        $this->insertContentRow($lessonId);

        return [$moduleId, $lessonId];
    }

    private function seedPublishedVersion(): string
    {
        $organisationId = $this->insertOrganisation('published-'.bin2hex(random_bytes(4)));
        $userId = $this->insertUser('published-'.bin2hex(random_bytes(4)).'@example.com');
        $courseId = $this->insertCourseRow($organisationId, $userId, 'published-'.bin2hex(random_bytes(4)));

        return $this->insertVersionRow($courseId, $userId, 1, 'published', $this->timestamp());
    }

    private function attemptUpdate(string $versionId, string $column): ?Exception
    {
        $values = [
            'title' => 'Changed title',
            'description' => 'Changed description',
            'version_number' => 99,
            'created_by' => '01900000-0000-7000-8000-000000009999',
            'course_id' => '01900000-0000-7000-8000-000000009998',
        ];

        if (!\array_key_exists($column, $values)) {
            throw new \InvalidArgumentException(sprintf('No test value for column "%s".', $column));
        }

        try {
            $this->connection()->executeStatement(
                sprintf('UPDATE course_versions SET %s = :value WHERE id = :id', $column),
                ['value' => $values[$column], 'id' => $versionId],
            );
        } catch (\Throwable $exception) {
            if ($exception instanceof Exception) {
                return $exception;
            }

            throw $exception;
        }

        return null;
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
}
