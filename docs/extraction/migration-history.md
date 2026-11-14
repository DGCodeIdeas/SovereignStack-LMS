# Migration History — Extraction Provenance

## DGLab source migrations (preserved, not copied)

The nine SQL migrations under `Spokes/Internal/Lms/migrations/` in DGLab remain in DGLab as an immutable, frozen reference. They are **not** copied into this repository. This follows the approved extraction strategy: the standalone LMS starts a fresh Doctrine Migrations history rather than replaying DGLab's schema.

| # | File | Purpose |
|---|---|---|
| 001 | `001_create_courses_table.sql` | Courses table (`CHAR(26)` random-hex IDs, `TIMESTAMP(6)`) |
| 002 | `002_create_modules_table.sql` | Modules table |
| 003 | `003_create_enrollments_table.sql` | Enrollments table |
| 004 | `004_create_progress_table.sql` | Progress table |
| 005 | `005_add_module_content.sql` | Module content columns |
| 006 | `006_create_course_versions_table.sql` | Course versions table |
| 007 | `007_add_course_version_column.sql` | Course version column |
| 008 | `008_enrollment_add_columns.sql` | Enrollment columns |
| 009 | `009_enrollment_indexes_fk.sql` | Enrollment indexes and foreign keys |

These files are provenance evidence only. They are not a migration path for this application.

## SovereignStack-LMS migration history

The independent history begins at:

- `migrations/Version20261114000000.php` — Phase 1 foundation schema.

It creates:

- `organisations`
- `users`
- `courses`
- `course_versions`
- `messenger_messages`
- `outbox_messages`
- `audit_events`
- `messenger_prototype_events`
- `messenger_prototype_failures`

### Corrections applied relative to the specification draft

1. **Outbox pending index.** The outbox uses `KEY idx_outbox_pending (status, available_at)` with no filtered-index `WHERE` clause. Neither MySQL 8.4 nor MariaDB 12.3 supports filtered indexes; the composite index serves the worker query.
2. **Course-version ownership.** `course_versions` has `UNIQUE KEY uq_cv_id_course (id, course_id)`. `courses` carries `current_published_version_id` plus `current_published_version_course_id`, and the composite foreign key `fk_courses_published_version (current_published_version_id, current_published_version_course_id) REFERENCES course_versions (id, course_id)` enforces that a published version belongs to the course identified in the denormalized column. The circular dependency is handled by creating `courses` first (without the composite FK), then `course_versions`, then adding the composite FK in a separate `ALTER TABLE`.
3. **Own-course binding.** The composite FK alone would permit a course row to store another course's id in the denormalized column. `CHECK chk_courses_published_version_ownership` closes that gap: both published-version columns must be NULL together, or both non-NULL with `current_published_version_course_id = id`. With the CHECK plus the composite FK, the database genuinely enforces that the current published version belongs to the owning course.
4. **Immutability and deletion semantics.** Published and archived versions are immutable. A `BEFORE DELETE` trigger (`trg_course_versions_prevent_immutable_delete`) raises SQLSTATE 45000 when `OLD.status IN ('published', 'archived')`. The composite FK uses `ON DELETE RESTRICT`, so even a referenced draft cannot be deleted; only an unreferenced draft can be removed. There is no trigger that clears a course's published-version reference. A published version therefore cannot be deleted through ordinary database operations, and the course's published-version reference remains intact. This also avoids MariaDB's rejection of `ON DELETE SET NULL` when the referencing columns participate in a CHECK. The trigger is dropped first in `down()`. Verified locally on MariaDB 11.8.6; MySQL 8.4 and MariaDB 12.3 verification is CI-pending.

### Storage conventions

- IDs: `CHAR(36)` UUIDv7 generated in PHP (`symfony/uid`).
- Timestamps: `DATETIME(6)` in UTC. `TIMESTAMP(6)` is not used.
- Charset/collation: `utf8mb4` / `utf8mb4_unicode_ci`, set explicitly on every table.
- JSON: `JSON` columns; MySQL stores binary JSON, MariaDB stores validated `LONGTEXT`.
- No database-generated UUIDs and no auto-increment fallback for domain identifiers.

### DGLab baseline

Source baseline: `543329ed84a2148308e0cc455ac7e2fe27cf35f4` (verified 2026-11-14). The superseded DGLab schema used `CHAR(26)` random hexadecimal identifiers and `TIMESTAMP(6)`; both are replaced in this repository.
