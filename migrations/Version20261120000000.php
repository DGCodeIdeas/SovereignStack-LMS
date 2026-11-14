<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 2B identity and organisation schema: user profiles, roles, permissions,
 * role/permission assignments, organisation memberships and single-use tokens.
 *
 * Roles and permissions are seeded with fixed UUIDs so that environments share
 * stable identifiers for authorisation decisions.
 */
final class Version20261120000000 extends AbstractMigration
{
    private const ROLES = [
        ['01900000-0000-7000-8000-000000000001', 'platform:admin', 'Platform Administrator'],
        ['01900000-0000-7000-8000-000000000002', 'lms:admin', 'LMS Administrator'],
        ['01900000-0000-7000-8000-000000000003', 'lms:instructor', 'Instructor'],
        ['01900000-0000-7000-8000-000000000004', 'lms:learner', 'Learner'],
    ];

    private const PERMISSIONS = [
        ['01900000-0000-7001-8000-000000000001', 'course.create'],
        ['01900000-0000-7001-8000-000000000002', 'course.update'],
        ['01900000-0000-7001-8000-000000000003', 'course.publish'],
        ['01900000-0000-7001-8000-000000000004', 'course.archive'],
        ['01900000-0000-7001-8000-000000000005', 'enrollment.create'],
        ['01900000-0000-7001-8000-000000000006', 'enrollment.cancel.self'],
        ['01900000-0000-7001-8000-000000000007', 'enrollment.cancel.other'],
        ['01900000-0000-7001-8000-000000000008', 'enrollment.view.self'],
        ['01900000-0000-7001-8000-000000000009', 'enrollment.view.any'],
        ['01900000-0000-7001-8000-000000000010', 'progress.record.self'],
        ['01900000-0000-7001-8000-000000000011', 'progress.view.self'],
        ['01900000-0000-7001-8000-000000000012', 'progress.view.any'],
        ['01900000-0000-7001-8000-000000000013', 'organisation.members.view'],
        ['01900000-0000-7001-8000-000000000014', 'organisation.members.manage'],
        ['01900000-0000-7001-8000-000000000015', 'user.roles.assign'],
        ['01900000-0000-7001-8000-000000000016', 'user.manage'],
        ['01900000-0000-7001-8000-000000000017', 'audit.view'],
        ['01900000-0000-7001-8000-000000000018', 'file.upload'],
        ['01900000-0000-7001-8000-000000000019', 'file.download'],
    ];

    private const INSTRUCTOR_PERMISSIONS = [
        'course.create',
        'course.update',
        'course.publish',
        'course.archive',
        'enrollment.view.any',
        'progress.view.any',
        'organisation.members.view',
        'file.upload',
        'file.download',
    ];

    private const LEARNER_PERMISSIONS = [
        'enrollment.create',
        'enrollment.cancel.self',
        'enrollment.view.self',
        'progress.record.self',
        'progress.view.self',
        'file.download',
    ];

    public function getDescription(): string
    {
        return 'Create Phase 2B identity and organisation schema (user_profiles, roles, permissions, role_permissions, user_roles, organisation_memberships, user_tokens) and seed roles/permissions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE user_profiles (
                id CHAR(36) NOT NULL,
                user_id CHAR(36) NOT NULL,
                display_name VARCHAR(255) NOT NULL,
                preferred_tz VARCHAR(64) NOT NULL DEFAULT \'UTC\',
                metadata JSON NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_user_profiles_user (user_id),
                CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addSql(
            'CREATE TABLE roles (
                id CHAR(36) NOT NULL,
                code VARCHAR(64) NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT NULL,
                created_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_roles_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addSql(
            'CREATE TABLE permissions (
                id CHAR(36) NOT NULL,
                code VARCHAR(128) NOT NULL,
                description TEXT NULL,
                created_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_permissions_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addSql(
            'CREATE TABLE role_permissions (
                role_id CHAR(36) NOT NULL,
                permission_id CHAR(36) NOT NULL,
                PRIMARY KEY (role_id, permission_id),
                CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
                CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addSql(
            'CREATE TABLE user_roles (
                id CHAR(36) NOT NULL,
                user_id CHAR(36) NOT NULL,
                role_id CHAR(36) NOT NULL,
                organisation_id CHAR(36) NULL,
                assigned_by CHAR(36) NULL,
                assigned_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                KEY idx_user_roles_user (user_id),
                KEY idx_user_roles_org (organisation_id),
                CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT,
                CONSTRAINT fk_ur_org FOREIGN KEY (organisation_id) REFERENCES organisations (id) ON DELETE RESTRICT,
                CONSTRAINT fk_ur_assigned_by FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addSql(
            'CREATE TABLE organisation_memberships (
                id CHAR(36) NOT NULL,
                organisation_id CHAR(36) NOT NULL,
                user_id CHAR(36) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'active\',
                joined_at DATETIME(6) NULL,
                ended_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_org_user (organisation_id, user_id),
                KEY idx_membership_user (user_id),
                CONSTRAINT fk_mem_org FOREIGN KEY (organisation_id) REFERENCES organisations (id) ON DELETE RESTRICT,
                CONSTRAINT fk_mem_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addSql(
            'CREATE TABLE user_tokens (
                id CHAR(36) NOT NULL,
                user_id CHAR(36) NOT NULL,
                purpose VARCHAR(32) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME(6) NOT NULL,
                consumed_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_user_tokens_hash (token_hash),
                KEY idx_user_tokens_user_purpose (user_id, purpose),
                KEY idx_user_tokens_expiry (expires_at),
                CONSTRAINT fk_user_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        foreach (self::ROLES as [$id, $code, $name]) {
            $this->addSql(sprintf(
                'INSERT IGNORE INTO roles (id, code, name, description, created_at) VALUES (%s, %s, %s, NULL, UTC_TIMESTAMP(6))',
                $this->quote($id),
                $this->quote($code),
                $this->quote($name),
            ));
        }

        foreach (self::PERMISSIONS as [$id, $code]) {
            $this->addSql(sprintf(
                'INSERT IGNORE INTO permissions (id, code, description, created_at) VALUES (%s, %s, NULL, UTC_TIMESTAMP(6))',
                $this->quote($id),
                $this->quote($code),
            ));
        }

        $roleIds = [
            'platform:admin' => '01900000-0000-7000-8000-000000000001',
            'lms:admin' => '01900000-0000-7000-8000-000000000002',
            'lms:instructor' => '01900000-0000-7000-8000-000000000003',
            'lms:learner' => '01900000-0000-7000-8000-000000000004',
        ];

        $permissionIds = [];
        foreach (self::PERMISSIONS as [$id, $code]) {
            $permissionIds[$code] = $id;
        }

        $assignments = [
            'platform:admin' => array_keys($permissionIds),
            'lms:admin' => array_keys($permissionIds),
            'lms:instructor' => self::INSTRUCTOR_PERMISSIONS,
            'lms:learner' => self::LEARNER_PERMISSIONS,
        ];

        foreach ($assignments as $roleCode => $permissionCodes) {
            foreach ($permissionCodes as $permissionCode) {
                $this->addSql(sprintf(
                    'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (%s, %s)',
                    $this->quote($roleIds[$roleCode]),
                    $this->quote($permissionIds[$permissionCode]),
                ));
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS user_tokens');
        $this->addSql('DROP TABLE IF EXISTS organisation_memberships');
        $this->addSql('DROP TABLE IF EXISTS user_roles');
        $this->addSql('DROP TABLE IF EXISTS role_permissions');
        $this->addSql('DROP TABLE IF EXISTS permissions');
        $this->addSql('DROP TABLE IF EXISTS roles');
        $this->addSql('DROP TABLE IF EXISTS user_profiles');
    }

    private function quote(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}
