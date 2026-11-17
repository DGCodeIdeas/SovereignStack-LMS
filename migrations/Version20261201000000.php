<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 2D enrollment and learning schema.
 *
 * This migration only appends new tables. Phase 1 (users, organisations,
 * courses, course_versions) and Phase 2B/2C (content, categories,
 * instructors) are untouched.
 *
 * The generated `_active_flag` column makes the "one active enrollment per
 * (learner, course)" rule a database guarantee while still allowing any number
 * of cancelled or completed historical enrollments: the unique index ignores
 * the NULL flag written for non-active rows.
 */
final class Version20261201000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Phase 2D enrollment and learning schema (enrollments, module_progress, lesson_progress, course_completions).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "CREATE TABLE enrollments (
                id CHAR(36) NOT NULL,
                organisation_id CHAR(36) NOT NULL,
                learner_user_id CHAR(36) NOT NULL,
                course_id CHAR(36) NOT NULL,
                course_version_id CHAR(36) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'active',
                enrolled_at DATETIME(6) NOT NULL,
                completed_at DATETIME(6) NULL,
                cancelled_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                _active_flag SMALLINT GENERATED ALWAYS AS (CASE WHEN status = 'active' THEN 1 ELSE NULL END) VIRTUAL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_active_enrollment (learner_user_id, course_id, _active_flag),
                KEY idx_enrollments_learner (learner_user_id, status),
                KEY idx_enrollments_course (course_id, status),
                KEY idx_enrollments_org (organisation_id),
                CONSTRAINT fk_enr_org FOREIGN KEY (organisation_id) REFERENCES organisations (id) ON DELETE RESTRICT,
                CONSTRAINT fk_enr_learner FOREIGN KEY (learner_user_id) REFERENCES users (id) ON DELETE RESTRICT,
                CONSTRAINT fk_enr_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE RESTRICT,
                CONSTRAINT fk_enr_version FOREIGN KEY (course_version_id) REFERENCES course_versions (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->addSql(
            "CREATE TABLE module_progress (
                id CHAR(36) NOT NULL,
                enrollment_id CHAR(36) NOT NULL,
                course_module_id CHAR(36) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'not_started',
                started_at DATETIME(6) NULL,
                completed_at DATETIME(6) NULL,
                updated_at DATETIME(6) NOT NULL,
                version INT NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                UNIQUE KEY uq_mp_enrollment_module (enrollment_id, course_module_id),
                KEY idx_mp_status (status),
                CONSTRAINT fk_mp_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments (id) ON DELETE CASCADE,
                CONSTRAINT fk_mp_module FOREIGN KEY (course_module_id) REFERENCES course_modules (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->addSql(
            "CREATE TABLE lesson_progress (
                id CHAR(36) NOT NULL,
                enrollment_id CHAR(36) NOT NULL,
                lesson_id CHAR(36) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'not_started',
                progress_data JSON NULL,
                started_at DATETIME(6) NULL,
                completed_at DATETIME(6) NULL,
                updated_at DATETIME(6) NOT NULL,
                version INT NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                UNIQUE KEY uq_lp_enrollment_lesson (enrollment_id, lesson_id),
                CONSTRAINT fk_lp_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments (id) ON DELETE CASCADE,
                CONSTRAINT fk_lp_lesson FOREIGN KEY (lesson_id) REFERENCES lessons (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->addSql(
            'CREATE TABLE course_completions (
                id CHAR(36) NOT NULL,
                enrollment_id CHAR(36) NOT NULL,
                learner_user_id CHAR(36) NOT NULL,
                course_id CHAR(36) NOT NULL,
                course_version_id CHAR(36) NOT NULL,
                completed_at DATETIME(6) NOT NULL,
                created_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_completion_enrollment (enrollment_id),
                KEY idx_completion_learner (learner_user_id),
                KEY idx_completion_course (course_id),
                CONSTRAINT fk_cc_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments (id) ON DELETE RESTRICT,
                CONSTRAINT fk_cc_learner FOREIGN KEY (learner_user_id) REFERENCES users (id) ON DELETE RESTRICT,
                CONSTRAINT fk_cc_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE RESTRICT,
                CONSTRAINT fk_cc_version FOREIGN KEY (course_version_id) REFERENCES course_versions (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS course_completions');
        $this->addSql('DROP TABLE IF EXISTS lesson_progress');
        $this->addSql('DROP TABLE IF EXISTS module_progress');
        $this->addSql('DROP TABLE IF EXISTS enrollments');
    }
}
