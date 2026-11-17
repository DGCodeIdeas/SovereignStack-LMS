<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Enrollment;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use SovereignStack\Lms\Tests\Integration\DatabaseIntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class EnrollmentSchemaTest extends DatabaseIntegrationTestCase
{
    use EnrollmentFixtures;

    private const TABLES = [
        'enrollments',
        'module_progress',
        'lesson_progress',
        'course_completions',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncateEnrollmentTables();
    }

    public function testEnrollmentAndLearningTablesExist(): void
    {
        foreach (self::TABLES as $table) {
            $count = $this->connection()->fetchOne(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
                ['table' => $table],
            );

            self::assertSame(1, self::asInt($count), sprintf('Table "%s" is missing.', $table));
        }
    }

    public function testColumnsUseExpectedTypesAndDefaults(): void
    {
        $expected = [
            ['enrollments', 'id', 'char(36)', null],
            ['enrollments', 'status', 'varchar(32)', null],
            ['enrollments', 'enrolled_at', 'datetime(6)', 6],
            ['enrollments', 'created_at', 'datetime(6)', 6],
            ['module_progress', 'id', 'char(36)', null],
            ['module_progress', 'version', 'int', null],
            ['module_progress', 'updated_at', 'datetime(6)', 6],
            ['lesson_progress', 'id', 'char(36)', null],
            ['lesson_progress', 'updated_at', 'datetime(6)', 6],
            ['course_completions', 'id', 'char(36)', null],
            ['course_completions', 'completed_at', 'datetime(6)', 6],
        ];

        foreach ($expected as [$table, $column, $type, $precision]) {
            $row = $this->connection()->fetchAssociative(
                'SELECT COLUMN_TYPE, DATETIME_PRECISION FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column',
                ['table' => $table, 'column' => $column],
            );

            self::assertIsArray($row, sprintf('Column "%s.%s" is missing.', $table, $column));
            $columnType = strtolower($this->rowString($row, 'COLUMN_TYPE'));

            if ('int' === $type) {
                self::assertMatchesRegularExpression('/^int(\(\d+\))?$/', $columnType);
            } else {
                self::assertSame($type, $columnType);
            }

            if (null !== $precision) {
                self::assertSame($precision, self::asInt($row['DATETIME_PRECISION'] ?? null));
            }
        }

        $lessonData = $this->connection()->fetchAssociative(
            "SELECT DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lesson_progress' AND COLUMN_NAME = 'progress_data'",
        );
        self::assertIsArray($lessonData);
        self::assertContains(
            strtolower($this->rowString($lessonData, 'DATA_TYPE')),
            ['json', 'longtext'],
        );

        $defaults = [
            ['enrollments', 'status', 'active'],
            ['module_progress', 'status', 'not_started'],
            ['module_progress', 'version', '1'],
            ['lesson_progress', 'status', 'not_started'],
            ['lesson_progress', 'version', '1'],
        ];

        foreach ($defaults as [$table, $column, $expectedDefault]) {
            $default = $this->connection()->fetchOne(
                'SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column',
                ['table' => $table, 'column' => $column],
            );

            self::assertIsString($default, sprintf('Default for "%s.%s" is missing.', $table, $column));
            self::assertSame($expectedDefault, trim($default, "'"));
        }
    }

    public function testActiveFlagIsGeneratedVirtual(): void
    {
        $row = $this->connection()->fetchAssociative(
            "SELECT EXTRA, GENERATION_EXPRESSION FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enrollments' AND COLUMN_NAME = '_active_flag'",
        );

        self::assertIsArray($row);
        $extra = strtolower($this->rowString($row, 'EXTRA'));
        self::assertStringContainsString('generated', $extra);
        self::assertStringContainsString('virtual', $extra);

        $expression = strtolower(str_replace(['`', '\\'], '', $this->rowString($row, 'GENERATION_EXPRESSION')));
        $expression = preg_replace('/_[a-z0-9]+\'/i', "'", $expression) ?? $expression;
        self::assertStringContainsString('case when', $expression);
        self::assertStringContainsString("status = 'active'", $expression);
    }

    public function testUniqueAndLookupIndexesExist(): void
    {
        $this->assertUniqueIndex('enrollments', 'uq_active_enrollment', ['learner_user_id', 'course_id', '_active_flag']);
        $this->assertUniqueIndex('module_progress', 'uq_mp_enrollment_module', ['enrollment_id', 'course_module_id']);
        $this->assertUniqueIndex('lesson_progress', 'uq_lp_enrollment_lesson', ['enrollment_id', 'lesson_id']);
        $this->assertUniqueIndex('course_completions', 'uq_completion_enrollment', ['enrollment_id']);

        $this->assertIndex('enrollments', 'idx_enrollments_learner', ['learner_user_id', 'status']);
        $this->assertIndex('enrollments', 'idx_enrollments_course', ['course_id', 'status']);
        $this->assertIndex('enrollments', 'idx_enrollments_org', ['organisation_id']);
        $this->assertIndex('module_progress', 'idx_mp_status', ['status']);
        $this->assertIndex('course_completions', 'idx_completion_learner', ['learner_user_id']);
        $this->assertIndex('course_completions', 'idx_completion_course', ['course_id']);
    }

    public function testForeignKeysExist(): void
    {
        $foreignKeys = [
            ['enrollments', 'fk_enr_org'],
            ['enrollments', 'fk_enr_learner'],
            ['enrollments', 'fk_enr_course'],
            ['enrollments', 'fk_enr_version'],
            ['module_progress', 'fk_mp_enrollment'],
            ['module_progress', 'fk_mp_module'],
            ['lesson_progress', 'fk_lp_enrollment'],
            ['lesson_progress', 'fk_lp_lesson'],
            ['course_completions', 'fk_cc_enrollment'],
            ['course_completions', 'fk_cc_learner'],
            ['course_completions', 'fk_cc_course'],
            ['course_completions', 'fk_cc_version'],
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

    public function testActiveFlagAllowsOnlyOneActiveEnrollmentPerLearnerAndCourse(): void
    {
        $context = $this->seedEnrollmentContext('schema');
        $first = $this->insertEnrollmentRow(
            $context['organisationId'],
            $context['learnerUserId'],
            $context['courseId'],
            $context['courseVersionId'],
            'active',
        );

        self::assertSame(1, self::asInt($this->connection()->fetchOne('SELECT _active_flag FROM enrollments WHERE id = :id', ['id' => $first])));

        $thrown = null;

        try {
            $this->insertEnrollmentRow(
                $context['organisationId'],
                $context['learnerUserId'],
                $context['courseId'],
                $context['courseVersionId'],
                'active',
            );
        } catch (UniqueConstraintViolationException $exception) {
            $thrown = $exception;
        }

        self::assertInstanceOf(UniqueConstraintViolationException::class, $thrown);

        $this->connection()->executeStatement(
            "UPDATE enrollments SET status = 'cancelled', cancelled_at = :now WHERE id = :id",
            ['now' => $this->timestamp(), 'id' => $first],
        );

        $cancelled = $this->insertEnrollmentRow(
            $context['organisationId'],
            $context['learnerUserId'],
            $context['courseId'],
            $context['courseVersionId'],
            'cancelled',
            null,
            null,
            $this->timestamp(),
        );

        $flag = $this->connection()->fetchOne(
            'SELECT _active_flag FROM enrollments WHERE id = :id',
            ['id' => $cancelled],
        );
        self::assertNull($flag);

        $secondActive = $this->insertEnrollmentRow(
            $context['organisationId'],
            $context['learnerUserId'],
            $context['courseId'],
            $context['courseVersionId'],
            'active',
        );

        self::assertSame(1, self::asInt($this->connection()->fetchOne('SELECT _active_flag FROM enrollments WHERE id = :id', ['id' => $secondActive])));
    }

    public function testDeletingEnrollmentCascadesProgress(): void
    {
        $context = $this->seedEnrollmentContext('cascade');
        $enrollmentId = $this->insertEnrollmentRow(
            $context['organisationId'],
            $context['learnerUserId'],
            $context['courseId'],
            $context['courseVersionId'],
        );
        $content = $this->seedModuleAndLesson($context['courseVersionId'], 'cascade');
        $now = $this->timestamp();

        $this->connection()->insert('module_progress', [
            'id' => Uuid::v7()->toRfc4122(),
            'enrollment_id' => $enrollmentId,
            'course_module_id' => $content['moduleId'],
            'status' => 'in_progress',
            'started_at' => $now,
            'completed_at' => null,
            'updated_at' => $now,
            'version' => 1,
        ]);
        $this->connection()->insert('lesson_progress', [
            'id' => Uuid::v7()->toRfc4122(),
            'enrollment_id' => $enrollmentId,
            'lesson_id' => $content['lessonId'],
            'status' => 'in_progress',
            'progress_data' => null,
            'started_at' => $now,
            'completed_at' => null,
            'updated_at' => $now,
            'version' => 1,
        ]);

        $this->connection()->executeStatement('DELETE FROM enrollments WHERE id = :id', ['id' => $enrollmentId]);

        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM module_progress WHERE enrollment_id = :id', ['id' => $enrollmentId]));
        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM lesson_progress WHERE enrollment_id = :id', ['id' => $enrollmentId]));
    }

    public function testCompletionRestrictsEnrollmentDeletion(): void
    {
        $context = $this->seedEnrollmentContext('restrict');
        $enrollmentId = $this->insertEnrollmentRow(
            $context['organisationId'],
            $context['learnerUserId'],
            $context['courseId'],
            $context['courseVersionId'],
        );
        $now = $this->timestamp();

        $this->connection()->insert('course_completions', [
            'id' => Uuid::v7()->toRfc4122(),
            'enrollment_id' => $enrollmentId,
            'learner_user_id' => $context['learnerUserId'],
            'course_id' => $context['courseId'],
            'course_version_id' => $context['courseVersionId'],
            'completed_at' => $now,
            'created_at' => $now,
        ]);

        $thrown = null;

        try {
            $this->connection()->executeStatement('DELETE FROM enrollments WHERE id = :id', ['id' => $enrollmentId]);
        } catch (ForeignKeyConstraintViolationException $exception) {
            $thrown = $exception;
        }

        self::assertInstanceOf(ForeignKeyConstraintViolationException::class, $thrown);
        self::assertSame(1, $this->fetchInt('SELECT COUNT(*) FROM enrollments WHERE id = :id', ['id' => $enrollmentId]));
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

        self::assertSame(
            $columns,
            array_map(fn (array $row): string => $this->rowString($row, 'COLUMN_NAME'), $rows),
            sprintf('Unique index "%s" on "%s" does not match.', $index, $table),
        );
    }

    /**
     * @param list<string> $columns
     */
    private function assertIndex(string $table, string $index, array $columns): void
    {
        $rows = $this->connection()->fetchAllAssociative(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
               AND INDEX_NAME = :index
             ORDER BY SEQ_IN_INDEX',
            ['table' => $table, 'index' => $index],
        );

        self::assertSame(
            $columns,
            array_map(fn (array $row): string => $this->rowString($row, 'COLUMN_NAME'), $rows),
            sprintf('Index "%s" on "%s" does not match.', $index, $table),
        );
    }
}
