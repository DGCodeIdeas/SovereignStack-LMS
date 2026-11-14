<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 1 schema: identity, organisation, course + version, Messenger transport,
 * transactional outbox, audit log and Messenger prototype support tables.
 *
 * Circular foreign keys between courses and course_versions are handled by
 * creating course_versions after courses and adding the composite FK from
 * courses.current_published_version_* in a separate ALTER TABLE statement.
 *
 * The specification asks for ON DELETE SET NULL on that composite FK, but
 * MariaDB refuses any referential action that writes a column referenced by a
 * CHECK constraint (error 1901: "Function or expression ... cannot be used in
 * the CHECK clause"). The FK therefore uses the default RESTRICT, and a
 * BEFORE DELETE trigger clears both published-version columns before the
 * version row disappears. The observable behaviour is identical: deleting a
 * published version leaves the course row intact with both columns NULL.
 */
final class Version20261114000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Phase 1 core schema (organisations, users, courses, course_versions, messenger, outbox, audit, prototype support tables).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE organisations (
                id CHAR(36) NOT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'active\',
                timezone VARCHAR(64) NOT NULL DEFAULT \'UTC\',
                settings JSON NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_organisations_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addSql(
            'CREATE TABLE users (
                id CHAR(36) NOT NULL,
                email VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'pending_verification\',
                email_verified_at DATETIME(6) NULL,
                failed_login_count INT NOT NULL DEFAULT 0,
                locked_until DATETIME(6) NULL,
                last_login_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_users_email (email),
                KEY idx_users_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addSql(
            'CREATE TABLE courses (
                id CHAR(36) NOT NULL,
                organisation_id CHAR(36) NOT NULL,
                owner_user_id CHAR(36) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                current_published_version_id CHAR(36) NULL,
                current_published_version_course_id CHAR(36) NULL,
                visibility VARCHAR(32) NOT NULL DEFAULT \'organisation_only\',
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_courses_org_slug (organisation_id, slug),
                KEY idx_courses_org (organisation_id),
                KEY idx_courses_published_version (current_published_version_id, current_published_version_course_id),
                CONSTRAINT fk_courses_org FOREIGN KEY (organisation_id) REFERENCES organisations (id) ON DELETE RESTRICT,
                CONSTRAINT fk_courses_owner FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE RESTRICT,
                CONSTRAINT chk_courses_published_version_ownership CHECK (
                    (current_published_version_id IS NULL AND current_published_version_course_id IS NULL)
                    OR (current_published_version_id IS NOT NULL AND current_published_version_course_id IS NOT NULL AND current_published_version_course_id = id)
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addSql(
            'CREATE TABLE course_versions (
                id CHAR(36) NOT NULL,
                course_id CHAR(36) NOT NULL,
                version_number INT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'draft\',
                published_at DATETIME(6) NULL,
                created_by CHAR(36) NOT NULL,
                created_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_cv_course_version (course_id, version_number),
                UNIQUE KEY uq_cv_id_course (id, course_id),
                KEY idx_cv_course (course_id),
                CONSTRAINT fk_cv_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE RESTRICT,
                CONSTRAINT fk_cv_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addSql(
            'ALTER TABLE courses ADD CONSTRAINT fk_courses_published_version FOREIGN KEY (current_published_version_id, current_published_version_course_id) REFERENCES course_versions (id, course_id) ON DELETE RESTRICT'
        );

        $this->addSql(
            'CREATE TRIGGER trg_course_versions_prevent_immutable_delete BEFORE DELETE ON course_versions FOR EACH ROW BEGIN IF OLD.status IN (\'published\', \'archived\') THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Published or archived course versions are immutable and cannot be deleted\'; END IF; END'
        );

        $this->addSql(
            'CREATE TABLE messenger_messages (
                id BIGINT AUTO_INCREMENT NOT NULL,
                body LONGTEXT NOT NULL,
                headers LONGTEXT NOT NULL,
                queue_name VARCHAR(190) NOT NULL,
                created_at DATETIME(6) NOT NULL,
                available_at DATETIME(6) NOT NULL,
                delivered_at DATETIME(6) NULL,
                PRIMARY KEY (id),
                INDEX idx_messenger_available (available_at),
                INDEX idx_messenger_queue (queue_name, available_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addSql(
            'CREATE TABLE outbox_messages (
                id CHAR(36) NOT NULL,
                event_id CHAR(36) NOT NULL,
                event_type VARCHAR(128) NOT NULL,
                aggregate_type VARCHAR(64) NOT NULL,
                aggregate_id CHAR(36) NOT NULL,
                organisation_id CHAR(36) NULL,
                payload JSON NOT NULL,
                payload_version INT NOT NULL DEFAULT 1,
                occurred_at DATETIME(6) NOT NULL,
                available_at DATETIME(6) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'pending\',
                attempts INT NOT NULL DEFAULT 0,
                last_error TEXT NULL,
                locked_at DATETIME(6) NULL,
                locked_by VARCHAR(128) NULL,
                published_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_outbox_event_id (event_id),
                KEY idx_outbox_pending (status, available_at),
                KEY idx_outbox_aggregate (aggregate_type, aggregate_id),
                KEY idx_outbox_org (organisation_id),
                KEY idx_outbox_status_locked (status, locked_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addSql(
            'CREATE TABLE audit_events (
                id CHAR(36) NOT NULL,
                event_type VARCHAR(128) NOT NULL,
                actor_user_id CHAR(36) NULL,
                organisation_id CHAR(36) NULL,
                resource_type VARCHAR(64) NOT NULL,
                resource_id CHAR(36) NOT NULL,
                event_data JSON NULL,
                ip_address VARCHAR(45) NULL,
                occurred_at DATETIME(6) NOT NULL,
                correlation_id CHAR(36) NOT NULL,
                PRIMARY KEY (id),
                KEY idx_audit_actor (actor_user_id, occurred_at),
                KEY idx_audit_org (organisation_id, occurred_at),
                KEY idx_audit_resource (resource_type, resource_id, occurred_at),
                KEY idx_audit_type (event_type, occurred_at),
                KEY idx_audit_correlation (correlation_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addSql(
            'CREATE TABLE messenger_prototype_events (
                event_id CHAR(36) NOT NULL,
                message_id CHAR(36) NOT NULL,
                payload VARCHAR(255) NOT NULL,
                processed_at DATETIME(6) NOT NULL,
                PRIMARY KEY (event_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addSql(
            'CREATE TABLE messenger_prototype_failures (
                id BIGINT AUTO_INCREMENT NOT NULL,
                event_id CHAR(36) NOT NULL,
                attempt_no INT NOT NULL,
                failed_at DATETIME(6) NOT NULL,
                error VARCHAR(1000) NOT NULL,
                PRIMARY KEY (id),
                KEY idx_mpf_event (event_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(Schema $schema): void
    {
        // Release the circular FK first: while courses.fk_courses_published_version
        // exists, InnoDB refuses to drop the referenced table course_versions, and
        // while course_versions rows exist it refuses to drop courses first.
        $this->addSql('DROP TRIGGER IF EXISTS trg_course_versions_prevent_immutable_delete');
        $this->addSql('ALTER TABLE courses DROP FOREIGN KEY fk_courses_published_version');
        $this->addSql('DROP TABLE IF EXISTS messenger_prototype_failures');
        $this->addSql('DROP TABLE IF EXISTS messenger_prototype_events');
        $this->addSql('DROP TABLE IF EXISTS audit_events');
        $this->addSql('DROP TABLE IF EXISTS outbox_messages');
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
        $this->addSql('DROP TABLE IF EXISTS course_versions');
        $this->addSql('DROP TABLE IF EXISTS courses');
        $this->addSql('DROP TABLE IF EXISTS users');
        $this->addSql('DROP TABLE IF EXISTS organisations');
    }
}
