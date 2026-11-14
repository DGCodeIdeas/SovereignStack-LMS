<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version0001InitialSchema extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: migration tracking, messenger transport, outbox, and shared infrastructure tables.';
    }

    public function up(Schema $schema): void
    {
        // messenger_messages — Symfony Messenger Doctrine transport
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (
                id              BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
                body            LONGTEXT     NOT NULL,
                headers         LONGTEXT     NOT NULL,
                queue_name      VARCHAR(190) NOT NULL,
                created_at      DATETIME(6)  NOT NULL,
                available_at    DATETIME(6)  NOT NULL,
                delivered_at    DATETIME(6)  NULL,
                INDEX idx_messenger_available (available_at),
                INDEX idx_messenger_queue (queue_name, available_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // outbox_messages — transactional outbox for reliable event delivery
        $this->addSql(<<<'SQL'
            CREATE TABLE outbox_messages (
                id              CHAR(36)      NOT NULL PRIMARY KEY,
                event_id        CHAR(36)      NOT NULL,
                event_type      VARCHAR(128)  NOT NULL,
                aggregate_type  VARCHAR(64)   NOT NULL,
                aggregate_id    CHAR(36)      NOT NULL,
                organisation_id CHAR(36)      NULL,
                payload         JSON          NOT NULL,
                payload_version INT           NOT NULL DEFAULT 1,
                occurred_at     DATETIME(6)   NOT NULL,
                available_at    DATETIME(6)   NOT NULL,
                status          VARCHAR(32)   NOT NULL DEFAULT 'pending',
                attempts        INT           NOT NULL DEFAULT 0,
                last_error      TEXT          NULL,
                locked_at       DATETIME(6)   NULL,
                locked_by       VARCHAR(128)  NULL,
                published_at    DATETIME(6)   NULL,
                created_at      DATETIME(6)   NOT NULL,
                UNIQUE KEY uq_outbox_event_id (event_id),
                KEY idx_outbox_status_available (status, available_at),
                KEY idx_outbox_aggregate (aggregate_type, aggregate_id),
                KEY idx_outbox_organisation (organisation_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // audit_events — append-only audit log
        $this->addSql(<<<'SQL'
            CREATE TABLE audit_events (
                id              CHAR(36)      NOT NULL PRIMARY KEY,
                event_type      VARCHAR(128)  NOT NULL,
                actor_user_id   CHAR(36)      NULL,
                organisation_id CHAR(36)      NULL,
                resource_type   VARCHAR(64)   NOT NULL,
                resource_id     CHAR(36)      NOT NULL,
                event_data      JSON          NULL,
                ip_address      VARCHAR(45)   NULL,
                occurred_at     DATETIME(6)   NOT NULL,
                correlation_id  CHAR(36)      NOT NULL,
                KEY idx_audit_actor (actor_user_id, occurred_at),
                KEY idx_audit_org (organisation_id, occurred_at),
                KEY idx_audit_resource (resource_type, resource_id, occurred_at),
                KEY idx_audit_type (event_type, occurred_at),
                KEY idx_audit_correlation (correlation_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS audit_events');
        $this->addSql('DROP TABLE IF EXISTS outbox_messages');
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
    }
}
