# Extraction Manifest — SovereignStack LMS

**Source baseline:** DGLab `origin/main` @ `543329ed84a2148308e0cc455ac7e2fe27cf35f4`
**Verified:** 2026-11-13 (SHA matches prior reported baseline)
**Target repository:** https://github.com/DGCodeIdeas/SovereignStack-LMS

---

## 1. Source inventory (DGLab `Spokes/Internal/Lms/`)

| Source path (relative to DGLab root) | Files | Lines | Disposition |
|---|---:|---:|---|
| `Spokes/Internal/Lms/Domain/Entity/` | 4 | 365 | PRESERVE (Phase 2+) |
| `Spokes/Internal/Lms/Domain/ValueObject/` | 7 | 210 | PRESERVE + ADAPT (UUIDv7) |
| `Spokes/Internal/Lms/Domain/Exception/` | 4 | 60 | PRESERVE |
| `Spokes/Internal/Lms/Domain/Repository/` | 4 | 150 | PRESERVE |
| `Spokes/Internal/Lms/Application/Command/` | 8 | 220 | PRESERVE |
| `Spokes/Internal/Lms/Application/*Interface.php` | 3 | 233 | PRESERVE |
| `Spokes/Internal/Lms/Application/Service/` | 3 | 485 | ADAPT (Symfony Security, DBAL) |
| `Spokes/Internal/Lms/Infrastructure/Persistence/` | 4 | 565 | REWRITE (DBAL) |
| `app/Controller/Lms/` | 4 | 420 | REPLACE (Symfony controllers) |
| `CLI/migrate.php` | 1 | 55 | REPLACE (Doctrine Migrations) |
| `CLI/seed.php` | 1 | 281 | REPLACE (broken; Symfony Console) |
| `openapi.yaml` | 1 | 673 | DOCUMENT (reference only) |
| `Spokes/Internal/Lms/migrations/001-009.sql` | 9 | 120 | DOCUMENT (provenance per ADR-002) |
| `Spokes/Internal/Lms/phpunit.xml.dist` | 1 | — | REPLACE |
| `Spokes/Internal/Lms/phpstan.neon` | 1 | — | REPLACE |
| `Products/LMS/Bootstrap.php` | 1 | 19 | OMIT (placeholder stub) |
| `Spokes/Internal/Lms/tests/Unit/` | 7 | 1883 | PRESERVE + ADAPT (Phase 2+) |

**Total:** 55 files, ~2,288 lines PHP source + ~1,883 lines tests

---

## 2. External DGLab dependencies (4 contracts, NOT copied)

| DGLab contract | Replacement in standalone LMS |
|---|---|
| `SovereignStack\Core\Database\ConnectionInterface` | `Doctrine\DBAL\Connection` (DBAL 4.5.x) |
| `SovereignStack\Hub\Identity\Application\IdentityInterface` | Symfony `Security` component + LMS-owned `CurrentUser` service (Phase 3) |
| `SovereignStack\Hub\Identity\Domain\ValueObject\UserId` | `SovereignStack\Lms\Identity\Domain\ValueObject\UserId` (fresh, Phase 3) |
| `SovereignStack\Hub\Identity\Domain\ValueObject\RoleIdentifier` | `SovereignStack\Lms\Identity\Domain\ValueObject\RoleIdentifier` (fresh, Phase 3) |

---

## 3. Phase 1 scope (this phase)

Phase 1 builds the **engineering foundation only**. No LMS business-module code is extracted.

| Deliverable | Status |
|---|---|
| Composer project (Symfony 7.4 LTS + PHP 8.4) | DONE |
| PSR-4 namespace `SovereignStack\Lms` | DONE |
| Doctrine DBAL 4.x + Doctrine Migrations | DONE |
| Symfony bootstrap (Kernel, console, index.php) | DONE |
| PHPUnit + PHPStan + architecture tests | DONE |
| UUIDv7 generation via `symfony/uid` | DONE |
| UTC `DATETIME(6)` timestamp policy | DONE |
| Health endpoints (`/health`, `/ready`) | DONE |
| Initial migration (messenger + outbox + audit tables) | DONE |
| Dual-database CI (MySQL 8.4 + MariaDB 12.3) | DONE |
| Messenger DBAL-only transport verification | DONE (CI test) |
| Extraction manifest (this document) | DONE |

---

## 4. Migration provenance (ADR-002)

The original 9 DGLab SQL migrations are preserved as extraction provenance. They are NOT executed in the standalone LMS. The standalone LMS uses fresh Doctrine Migration PHP classes.

| DGLab migration | Content | Standalone equivalent |
|---|---|---|
| `001_create_courses_table.sql` | `lms_courses` table | Phase 2 migration (with `organisation_id`, UUIDv7 `CHAR(36)`, composite FK) |
| `002_create_modules_table.sql` | `lms_modules` table | Phase 2 migration (with `course_version_id`, `is_required`) |
| `003_create_enrollments_table.sql` | `lms_enrollments` table | Phase 2 migration (with `organisation_id`, `cancelled_at`, generated column) |
| `004_create_progress_table.sql` | `lms_progress` table | Phase 2 migration (UUID PK, `version` for optimistic locking) |
| `005_add_module_content.sql` | ALTER modules ADD content | Phase 2 (lesson_contents table) |
| `006_create_course_versions_table.sql` | `lms_course_versions` table | Phase 2 (full course_versions entity) |
| `007_add_course_version_column.sql` | ALTER courses ADD version | NOT carried forward (redundant per §5.4.1) |
| `008_enrollment_add_columns.sql` | ALTER enrollments ADD course_version + _active_flag | Phase 2 (generated column pattern preserved) |
| `009_enrollment_indexes_fk.sql` | Rebuild enrollment indexes/FKs | Phase 2 (composite unique key) |

---

## 5. Deferred to Phase 2+

| Phase | Content |
|---|---|
| Phase 2 | Shared infrastructure (Clock, file storage abstraction, transaction management); full schema migrations (users, organisations, courses, enrollments, progress, completions, audit, notifications, files) |
| Phase 3 | Identity module (users, authentication, roles, permissions); Organisation module |
| Phase 4 | Course module (courses, versions, modules, lessons, HugeRTE); extraction of DGLab Course domain |
| Phase 5 | Enrollment + Learning modules; extraction of DGLab Enrollment/Progress domain; CompleteEnrollmentHandler |
| Phase 6 | Notifications, Messenger workers, outbox dispatch |
| Phase 7 | Administration (reporting, search, CSV, audit browsing) |
| Phase 8 | Production readiness (security, performance, backup, monitoring) |
| Phase 9 | Release |

---

*This manifest is a living document. It is updated as each phase completes.*
