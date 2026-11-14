# Phase 1 Implementation Report

**Date:** 2026-11-14
**Branch:** `feature/phase-1-symfony-foundation-v2`
**Base:** `main` at `b9b492e39c16211da50d025ff227b337b696adf4`

## Environment

| Tool | Result |
|---|---|
| PHP | 8.4.27 (CLI, NTS) |
| Composer | 2.10.2 |
| Git | 2.53.0 |
| GitHub CLI | 2.101.0 (authenticated as DGCodeIdeas, admin on both repositories) |
| Docker | 29.7.2 client; daemon running but socket is `root:docker 660`; user is not in `docker` group → unusable |
| Local database | MariaDB 11.8.6 (private user-owned instance used for the smoke test on port 3307) |
| MySQL 8.4 | Not installed locally |
| PHP `intl` / `bcmath` | Not installed for PHP 8.4 locally; installed in CI |

## Implemented

- Symfony 7.4.23 application kernel and runtime bootstrap (`public/index.php`, `bin/console`).
- Composer project with committed `composer.lock`; every `symfony/*` runtime component is constrained to and locked on the 7.4 LTS line.
- Doctrine DBAL 4.5.1 connection (no Doctrine ORM; CI has an explicit ORM-absence gate).
- Doctrine Migrations 3.9.8 with `migrations/Version20261114000000.php`.
- Schema foundation: organisations, users, courses, course_versions, messenger_messages, outbox_messages, audit_events, Messenger prototype tables.
- Outbox index corrected to `KEY idx_outbox_pending (status, available_at)` (no filtered index).
- Course-version ownership enforced by a composite unique key, a composite foreign key, and a CHECK constraint that requires the denormalized course id to equal the course's own id. A `BEFORE DELETE` trigger preserves the intended `ON DELETE SET NULL` behaviour without MariaDB's CHECK limitation.
- Symfony Messenger DBAL-only prototype:
  - `doctrine://default` async transport and `failed` transport, both on the migration-managed `messenger_messages` table (`auto_setup: false`).
  - Dispatch/persistence, real worker consumption, retry, failure transport, idempotency, and caller-transaction participation tests.
- Health endpoints: `GET /health` (liveness) and `GET /ready` (database readiness; no raw error leakage).
- Unit tests (UUIDv7, clock), architecture tests (module boundaries, dependency direction, LTS tree, ORM absence), migration/constraint tests, Messenger integration tests, and a functional health test.
- GitHub Actions: quality job plus MySQL 8.4 / MariaDB 12.3 integration matrix.
- PHPStan level max and PHP-CS-Fixer configuration.

## Committed

**PENDING.** The orchestrator commits and pushes this branch after verification.

## Tested locally

Evidence from PHP 8.4.27, Composer 2.10.2, and a private MariaDB 11.8.6 instance:

| Check | Result |
|---|---|
| `composer validate --strict` | `./composer.json is valid` |
| PHP syntax (`php -l`, all files) | 24 files, no syntax errors |
| YAML lint | `[OK] All 7 YAML files contain valid syntax` |
| Container lint (dev + test) | `[OK]` both environments |
| PHPStan level max | `[OK] No errors` |
| PHP-CS-Fixer dry run | `Found 0 of 23 files that can be fixed` |
| Unit suite | 6 tests, 53 assertions, pass |
| Architecture suite | 12 tests, 78 assertions, pass |
| Integration suite (MariaDB 11.8.6) | 22 tests, 88 assertions, pass |
| Migration up | 1 migration, 11 SQL statements, pass |
| Migration rollback (`prev`) | pass; schema returned to 0 |
| Migration re-apply | pass |
| Messenger retry/failure transport | pass within integration suite |
| Composite FK + CHECK rejection | pass within integration suite |
| Version deletion nulls both columns | pass within integration suite |

The PHPUnit runner reports 2–5 deprecation notices about doc-comment metadata; these are not failures and do not trip `failOnWarning`.

## Tested in CI

**PENDING.** CI has not run on this branch yet.

## Not tested locally

- MySQL 8.4 integration (no local server; CI only).
- MariaDB 12.3 integration (local instance is 11.8.6; CI only).
- Messenger worker integration against the exact target engines (CI only).

The local MariaDB 11.8.6 result is a smoke test, not evidence of MySQL 8.4 or MariaDB 12.3 compatibility.

## Blocked

No Phase 1 blocker. Local execution against the exact target engines requires either:
- credentials for the host MariaDB (admin action), or
- membership of the `docker` group / root access to start the Docker daemon, or
- a private MySQL 8.4 installation.

None of these is required to complete Phase 1: GitHub Actions provides both mandated engines.

## Evidence table

| Check | Environment | Status | Evidence |
|---|---|---|---|
| Composer validation | Local | PASS | `./composer.json is valid` |
| Symfony tree is 7.4.x | Local | PASS | `composer.lock` inventory; architecture test |
| Container lint | Local | PASS | dev + test `[OK]` |
| PHPStan level max | Local | PASS | `[OK] No errors` |
| PHP-CS-Fixer | Local | PASS | 0 of 23 fixable |
| Unit tests | Local | PASS | 6 tests / 53 assertions |
| Architecture tests | Local | PASS | 12 tests / 78 assertions |
| Migration on MariaDB 11.8.6 | Local | PASS | 11 SQL statements; rollback/re-apply pass |
| Integration tests on MariaDB 11.8.6 | Local | PASS | 22 tests / 88 assertions |
| Messenger retry/failure transport | Local | PASS | integration suite |
| Composite FK + CHECK rejection | Local | PASS | integration suite |
| Migration on MySQL 8.4 | CI | PENDING | |
| Migration on MariaDB 12.3 | CI | PENDING | |
| Integration tests on MySQL 8.4 | CI | PENDING | |
| Integration tests on MariaDB 12.3 | CI | PENDING | |

No test result is claimed without execution evidence. CI rows will be updated only after the workflow actually completes.
