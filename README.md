# SovereignStack LMS

**Standalone Learning Management System** — an independent, production-ready LMS extracted from [DGLab](https://github.com/DGCodeIdeas/DGLab).

[![CI](https://github.com/DGCodeIdeas/SovereignStack-LMS/actions/workflows/ci.yml/badge.svg)](https://github.com/DGCodeIdeas/SovereignStack-LMS/actions/workflows/ci.yml)

## Status

**Phase 1, Gate 2A and Gate 2B are merged to `main`.** Gate 2C (Course and Content) is implemented on `feature/phase-2c-course-content` and is pending review in PR #5.

- **Specification version:** 2.1 (revised)
- **Architecture decisions:** 163 approved (A-001–A-040, D-001–D-040, E-001–E-040, ADR-001–ADR-040, DB-041–DB-043)
- **Source baseline:** DGLab `543329ed84a2148308e0cc455ac7e2fe27cf35f4` (verified 2026-11-14); the handover's `25d4e96…`/`b153ab8…` were stale/incorrect, and `543329e…` is the verified shared `main`.

## Technology stack

| Layer | Technology |
|---|---|
| Language | PHP 8.4 |
| Framework | Symfony 7.4.23 |
| Architecture | Modular Monolith |
| Database access | Doctrine DBAL 4.5.1 (no ORM) |
| Migrations | Doctrine Migrations 3.9.8 |
| Primary database | MySQL 8.4 LTS |
| Alternative database | MariaDB 12.3 LTS (first-class supported) |
| Test framework | PHPUnit 11.5 |
| Static analysis | PHPStan 1.12 |
| UUID generation | Symfony UID (`symfony/uid`), UUIDv7 |
| Rich-text editor | HugeRTE (MIT-licensed) |
| Frontend | Twig + Bootstrap 5.3 |
| CI/CD | GitHub Actions |

## Quick start

```bash
composer install
cp .env .env.local   # .env is committed for local development; use .env.local for overrides
php bin/console doctrine:migrations:migrate
php -S 127.0.0.1:8000 -t public
# or, with the Symfony CLI:
symfony server:start
```

## Local development server

A persistent local Apache instance is available on **http://127.0.0.1:8082**:

```bash
./scripts/lms-server enable      # install + enable at boot + start (pkexec)
./scripts/lms-server toggle      # start/stop
./scripts/lms-server status      # service + /health + /ready
./scripts/lms-server logs
./scripts/lms-server disable     # stop + remove boot autostart
```

Privileged actions use `pkexec`, never `sudo`. See
[`docs/LOCAL-SERVER.md`](docs/LOCAL-SERVER.md) for paths, persistence,
troubleshooting and port changes.

## Test commands

| Command | Purpose |
|---|---|
| `composer test` | Unit and architecture suites |
| `composer test:integration` | Integration suite (requires `DATABASE_URL`) |
| `composer static-analysis` | PHPStan at level max |
| `composer cs-check` | PHP-CS-Fixer dry run |

## Database support

Both **MySQL 8.4** and **MariaDB 12.3** are mandatory targets. Integration tests must run against both engines; CI enforces this with a matrix. SQLite is not acceptable.

## Health endpoints

| Endpoint | Purpose |
|---|---|
| `/health` | Liveness probe |
| `/ready` | Readiness probe (checks the database) |

## Documentation

The complete architecture specification and supporting deliverables are in [`docs/`](docs/):

- [`docs/SHARED-INFRASTRUCTURE.md`](docs/SHARED-INFRASTRUCTURE.md) — Shared infrastructure: transactions, results, problem responses, correlation IDs, file storage
- [`docs/IDENTITY-ORGANISATION.md`](docs/IDENTITY-ORGANISATION.md) — Identity and organisation: schema, RBAC seed catalogue, authentication, tokens, security, and organisation isolation
- [`docs/COURSE-CONTENT.md`](docs/COURSE-CONTENT.md) — Course content: schema, lifecycle, version immutability, ordering, authorisation, sanitisation and presentation routes
- [`docs/LOCAL-SERVER.md`](docs/LOCAL-SERVER.md) — Persistent local Apache/systemd toggle, pkexec usage and troubleshooting
- [`docs/LOCAL-DATABASE-TUNING.md`](docs/LOCAL-DATABASE-TUNING.md) — Local MariaDB baseline, measurements and production-only recommendations
- [`docs/SPEC-001-v2.1.md`](docs/SPEC-001-v2.1.md) — Complete architecture specification (160 + 3 decisions)
- [`docs/DELIVERABLE-B-CHANGE-SUMMARY.md`](docs/DELIVERABLE-B-CHANGE-SUMMARY.md) — Change summary
- [`docs/DELIVERABLE-C-TRACEABILITY-MATRIX.md`](docs/DELIVERABLE-C-TRACEABILITY-MATRIX.md) — Decision traceability matrix
- [`docs/DELIVERABLE-D-PHASE1-READINESS.md`](docs/DELIVERABLE-D-PHASE1-READINESS.md) — Phase 1 readiness report
- [`docs/DELIVERABLE-E-OUTSTANDING-QUESTIONS.md`](docs/DELIVERABLE-E-OUTSTANDING-QUESTIONS.md) — Outstanding questions
- [`docs/PHASE-1-IMPLEMENTATION-REPORT.md`](docs/PHASE-1-IMPLEMENTATION-REPORT.md) — Phase 1 implementation report
- [`docs/extraction/EXTRACTION-MANIFEST.md`](docs/extraction/EXTRACTION-MANIFEST.md) — Extraction manifest
- [`docs/extraction/migration-history.md`](docs/extraction/migration-history.md) — Migration history

## Extraction strategy

**Copy, not move.** The LMS implementation in DGLab becomes a frozen reference copy after extraction. This repository becomes the authoritative implementation. There is no ongoing two-way synchronisation between the repositories.

## License

Apache-2.0. See [`LICENSE`](LICENSE).

## Repository governance

- Protected `main` branch (to be configured)
- Reviewed pull requests required
- All 163 architectural decisions are the approved baseline
- Implementation proceeds phase by phase against that baseline