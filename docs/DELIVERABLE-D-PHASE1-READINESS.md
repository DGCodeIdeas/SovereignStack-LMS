# Deliverable D — Phase 1 Readiness Report (revised)

**Assessment date:** 2026-11-13
**Specification:** SPEC-001 v2.1 (revised)
**Assessment mode:** Documentation review + external verification. No code execution.

---

## Executive summary

**Overall Phase 1 readiness: READY SUBJECT TO SPECIFIED CONDITIONS**

No BLOCKED items. Five conditions must be satisfied during Phase 1 execution:

1. Reverify source baseline SHA at Phase 1 start.
2. Complete Messenger DBAL-only prototype (message persistence, consumption, retry, failure transport, transaction, migration-managed table, idempotent handling) against real MySQL 8.4 AND MariaDB 12.3.
3. Establish dual-database test infrastructure (MySQL 8.4 + MariaDB 12.3 containers in CI).
4. Verify MariaDB 12.3 `innodb_snapshot_isolation=ON` behaviour in completion concurrency tests.
5. Run full `composer audit` before public repository publication.

---

## Readiness by area

### 1. Symfony + PHP — VERIFIED

| Item | Status | Evidence |
|---|---|---|
| Symfony 7.4 LTS released Nov 2025 | VERIFIED | symfony.com/releases/7.4 |
| Minimum PHP 8.2 | VERIFIED | symfony.com/releases/7.4 |
| PHP 8.4 compatible | VERIFIED | 8.4 > 8.2 minimum |
| Bug fixes through Nov 2028 | VERIFIED | symfony.com/releases |
| Security through Nov 2029 | VERIFIED | symfony.com/releases |

**Blocks?** NO.

### 2. Doctrine DBAL + Migrations — VERIFIED (corrected)

| Item | Status | Evidence |
|---|---|---|
| DBAL 4.5.x requires PHP ^8.2 (corrected) | VERIFIED | packagist.org/packages/doctrine/dbal |
| PHP 8.4 compatible with DBAL 4.5.x | VERIFIED | 8.4 > 8.2 minimum |
| Doctrine Migrations compatible with DBAL 4.x | VERIFIED | packagist |
| MariaDB platform detection | PARTIALLY VERIFIED | DBAL auto-detects; Phase 1 verifies SQL generation |
| Both engines pass integration tests | NOT VERIFIED | Phase 1 task |

**Blocks?** NO.

### 3. MariaDB 12.3 LTS — VERIFIED

| Item | Status | Evidence |
|---|---|---|
| MariaDB 12.3 LTS exists | VERIFIED | mariadb.org/mariadb-server-12-3-lts-released |
| Released May 2026, maintained until June 2029 | VERIFIED | endoflife.date/mariadb |
| `innodb_snapshot_isolation=ON` by default | VERIFIED | mariadb.org release notes |
| JSON stored as LONGTEXT (vs MySQL binary) | VERIFIED | mariadb.com/docs, skeema.io |
| Compatibility assessment complete (§18b) | VERIFIED | 17-feature comparison table |

**Blocks?** NO.

### 4. Symfony UID (UUIDv7) — VERIFIED

| Item | Status | Evidence |
|---|---|---|
| symfony/uid provides UUIDv7 | VERIFIED | symfony.com/doc/current/components/uid.html |
| Symfony 7.4 generates UUIDv7 by default | VERIFIED | symfony.com/blog/new-in-symfony-7-4-uid-improvements (Oct 2025) |
| symfony/uid compatible with PHP 8.2+ | VERIFIED | packagist.org/packages/symfony/uid |
| CHAR(36) storage works on both engines | VERIFIED | Standard UUID format |

**Blocks?** NO.

### 5. Messenger DBAL-only — PARTIALLY VERIFIED

| Item | Status | Evidence |
|---|---|---|
| `symfony/doctrine-messenger` standalone package | VERIFIED | symfony.com/doc/current/messenger.html |
| No ORM dependency at package level | VERIFIED | Package composer.json |
| Works with DBAL only | PARTIALLY VERIFIED | Historical issues resolved (2023+ evidence) |
| Prototype against MySQL 8.4 | NOT VERIFIED | Phase 1 task |
| Prototype against MariaDB 12.3 | NOT VERIFIED | Phase 1 task |

**Blocks?** NO (Phase 1 prototype mandatory before Phase 6).

### 6. Transactional outbox — NOT VERIFIED (Phase 1/6 task)

| Item | Status |
|---|---|
| Schema specified (§5.11) | VERIFIED |
| Lifecycle specified (§5.11.1) | VERIFIED |
| `FOR UPDATE SKIP LOCKED` on both engines | VERIFIED (both support) |
| Prototype against both engines | NOT VERIFIED |

**Blocks?** NO (Phase 6 implementation).

### 7. Database portability — PARTIALLY VERIFIED

| Item | Status |
|---|---|
| Compatibility assessment (§18b) | VERIFIED |
| Dual-database CI matrix specified (§11.4) | VERIFIED |
| Portable search interface specified (§10.1) | VERIFIED |
| Both engines tested | NOT VERIFIED (Phase 1 task) |

**Blocks?** NO.

### 8. Remaining areas (unchanged from prior assessment)

- Schema + migration strategy: VERIFIED
- Test infrastructure: NOT VERIFIED (Phase 1 task)
- Security baseline: VERIFIED
- Composer licence audit: NOT VERIFIED (Phase 1 task)
- GitHub Actions gates: VERIFIED (spec complete)
- Deployment assumptions: VERIFIED
- Extraction provenance: VERIFIED (reverify at Phase 1 start)

---

## Conditions for Phase 1

1. **Reverify source baseline** — fetch latest `origin/main`, confirm SHA.
2. **Messenger DBAL-only prototype** — against BOTH MySQL 8.4 AND MariaDB 12.3.
3. **Dual-database test infrastructure** — MySQL 8.4 + MariaDB 12.3 containers in CI.
4. **MariaDB `innodb_snapshot_isolation` verification** — test completion concurrency against MariaDB 12.3 specifically.
5. **`composer audit`** — before public repository publication.

**No BLOCKED items. No technical blocker prevents starting Phase 1.**

---

## Distinguishing documentation review from execution

This is a **documentation review + external source verification**. No code executed. No MySQL/MariaDB instances provisioned. VERIFIED means "confirmed against official documentation," NOT "tested in our environment."

---

*End of Deliverable D (revised).*
