<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 2E learner catalogue permission.
 *
 * This migration only appends the `course.view` permission and maps it to the
 * four seeded roles. It does not alter the Phase 1/2B/2C/2D schema.
 */
final class Version20261215000000 extends AbstractMigration
{
    private const PERMISSION_ID = '01900000-0000-7001-8000-000000000020';
    private const PERMISSION_CODE = 'course.view';
    private const PERMISSION_DESCRIPTION = 'View published course catalogue entries and course details.';

    private const ROLE_IDS = [
        '01900000-0000-7000-8000-000000000001',
        '01900000-0000-7000-8000-000000000002',
        '01900000-0000-7000-8000-000000000003',
        '01900000-0000-7000-8000-000000000004',
    ];

    public function getDescription(): string
    {
        return 'Add the course.view permission and map it to platform:admin, lms:admin, lms:instructor and lms:learner.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(sprintf(
            'INSERT IGNORE INTO permissions (id, code, description, created_at) VALUES (%s, %s, %s, UTC_TIMESTAMP(6))',
            $this->quote(self::PERMISSION_ID),
            $this->quote(self::PERMISSION_CODE),
            $this->quote(self::PERMISSION_DESCRIPTION),
        ));

        foreach (self::ROLE_IDS as $roleId) {
            $this->addSql(sprintf(
                'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (%s, %s)',
                $this->quote($roleId),
                $this->quote(self::PERMISSION_ID),
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DELETE FROM role_permissions WHERE permission_id = :permission_id',
            ['permission_id' => self::PERMISSION_ID],
        );
        $this->addSql(
            'DELETE FROM permissions WHERE id = :permission_id',
            ['permission_id' => self::PERMISSION_ID],
        );
    }

    private function quote(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}
