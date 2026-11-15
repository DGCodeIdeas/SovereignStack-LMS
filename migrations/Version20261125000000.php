<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 2C course content schema: modules, lessons, lesson content, categories,
 * category assignments and instructor assignments.
 *
 * Phase 1 already created courses and course_versions. This migration only
 * appends new tables and a second immutability trigger; it never alters the
 * Phase 1 columns, indexes or constraints.
 *
 * The trigger keeps published and archived course versions frozen with respect
 * to content (course, version number, title, description and creator) while
 * still allowing the lifecycle transitions the domain needs: publishing sets
 * published_at, and archiving moves the status from published to archived.
 */
final class Version20261125000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Phase 2C course content schema (course_modules, lessons, lesson_contents, course_categories, course_category_assignments, course_instructors) and the course version immutability trigger.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE course_modules (
                id CHAR(36) NOT NULL,
                course_version_id CHAR(36) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                position INT NOT NULL,
                is_required TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_cm_version_position (course_version_id, position),
                KEY idx_cm_version (course_version_id),
                CONSTRAINT fk_cm_version FOREIGN KEY (course_version_id) REFERENCES course_versions (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addSql(
            'CREATE TABLE lessons (
                id CHAR(36) NOT NULL,
                course_module_id CHAR(36) NOT NULL,
                title VARCHAR(255) NOT NULL,
                position INT NOT NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_lessons_module_position (course_module_id, position),
                KEY idx_lessons_module (course_module_id),
                CONSTRAINT fk_lessons_module FOREIGN KEY (course_module_id) REFERENCES course_modules (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addSql(
            'CREATE TABLE lesson_contents (
                id CHAR(36) NOT NULL,
                lesson_id CHAR(36) NOT NULL,
                content_html LONGTEXT NOT NULL,
                content_metadata JSON NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_lc_lesson (lesson_id),
                CONSTRAINT fk_lc_lesson FOREIGN KEY (lesson_id) REFERENCES lessons (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addSql(
            'CREATE TABLE course_categories (
                id CHAR(36) NOT NULL,
                organisation_id CHAR(36) NOT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                created_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_cat_org_slug (organisation_id, slug),
                CONSTRAINT fk_cat_org FOREIGN KEY (organisation_id) REFERENCES organisations (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addSql(
            'CREATE TABLE course_category_assignments (
                course_id CHAR(36) NOT NULL,
                category_id CHAR(36) NOT NULL,
                PRIMARY KEY (course_id, category_id),
                KEY idx_cca_cat (category_id),
                CONSTRAINT fk_cca_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE,
                CONSTRAINT fk_cca_cat FOREIGN KEY (category_id) REFERENCES course_categories (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addSql(
            'CREATE TABLE course_instructors (
                id CHAR(36) NOT NULL,
                course_id CHAR(36) NOT NULL,
                user_id CHAR(36) NOT NULL,
                assigned_by CHAR(36) NOT NULL,
                assigned_at DATETIME(6) NOT NULL,
                effective_from DATETIME(6) NOT NULL,
                effective_until DATETIME(6) NULL,
                PRIMARY KEY (id),
                KEY idx_instr_course (course_id),
                KEY idx_instr_user (user_id),
                CONSTRAINT fk_instr_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE RESTRICT,
                CONSTRAINT fk_instr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
                CONSTRAINT fk_instr_assigned_by FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addSql(
            'CREATE TRIGGER trg_course_versions_prevent_content_update
            BEFORE UPDATE ON course_versions FOR EACH ROW
            BEGIN
                IF OLD.status IN (\'published\', \'archived\') AND (
                    NEW.course_id <> OLD.course_id OR NEW.version_number <> OLD.version_number OR
                    NEW.title <> OLD.title OR NOT (NEW.description <=> OLD.description) OR
                    NEW.created_by <> OLD.created_by
                ) THEN
                    SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Published or archived course versions are immutable\';
                END IF;
            END'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS trg_course_versions_prevent_content_update');
        $this->addSql('DROP TABLE IF EXISTS course_instructors');
        $this->addSql('DROP TABLE IF EXISTS course_category_assignments');
        $this->addSql('DROP TABLE IF EXISTS course_categories');
        $this->addSql('DROP TABLE IF EXISTS lesson_contents');
        $this->addSql('DROP TABLE IF EXISTS lessons');
        $this->addSql('DROP TABLE IF EXISTS course_modules');
    }
}
