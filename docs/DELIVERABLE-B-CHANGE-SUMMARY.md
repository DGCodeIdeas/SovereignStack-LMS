# Deliverable B — Change Summary (v2.0 → v2.1 revised)

This document lists all material changes between SPEC-001 v2.0 and the revised v2.1, incorporating DB-041/042/043 and all corrections from the independent review.

---

## 1. Approved new decisions (3)

| Decision | Approved | SPEC section |
|---|---|---|
| **DB-041** Primary database: MySQL 8.4 LTS | Yes | §3.1, §20 |
| **DB-042** UUID generation: Symfony UID (`symfony/uid`), UUIDv7 | Yes | §3.1, §20.5 |
| **DB-043** First-class MariaDB 12.3 LTS support | Yes | §3.1, §18b, §20 |

Total approved decisions: **163** (160 original + 3 new DB decisions).

---

## 2. Corrected technical claims

### 2.1 Doctrine DBAL PHP requirement (corrected)

**Prior claim (incorrect):** "Doctrine DBAL 4.x requires PHP 8.4+"

**Corrected claim:** "Doctrine DBAL 4.5.x requires PHP ^8.2"

**Source:** packagist.org/packages/doctrine/dbal — "Requires php: ^8.2"
**Date verified:** 2026-11-13
**Impact:** PHP 8.4 is compatible (8.4 > 8.2 minimum). No change to approved architecture.

### 2.2 Symfony 7.4 LTS release date and PHP requirement (corrected in prior revision, retained)

**Prior claim (incorrect):** "Released November 2026, requires PHP 8.4+"

**Corrected claim:** "Released November 2025, minimum PHP 8.2.0"

**Source:** symfony.com/releases/7.4
**Date verified:** 2026-11-13

### 2.3 TIMESTAMP(6) vs DATETIME(6) storage comparison (corrected)

**Prior claim (incorrect):** "TIMESTAMP = 4 bytes, DATETIME = 8 bytes"

**Corrected claim:** "TIMESTAMP(6) = 7 bytes (4 + 3 fractional), DATETIME(6) = 8 bytes (5 + 3 fractional) — difference is only 1 byte"

**Additional correction:** TIMESTAMP range differs between engines (MySQL 1970-2038, MariaDB 1970-2106 on 64-bit). This is itself a portability risk, reinforcing the DATETIME(6) choice.

### 2.4 Verification dates (corrected)

**Prior dates:** November 14, 2026

**Corrected dates:** November 13, 2026 (current date)

**Date types distinguished:**
- Source publication date: when the documentation was published
- Date accessed: when Z.ai verified the claim (2026-11-13)
- Date test executed: N/A (no tests executed — documentation review only)
- Date report generated: 2026-11-13

---

## 3. Added material

### 3.1 Database support matrix (§3.1)
- MySQL 8.4 LTS: primary reference, mandatory CI target, maintained until April 2032
- MariaDB 12.3 LTS: first-class supported alternative, mandatory CI target, maintained until June 2029
- Both are mandatory; MariaDB is NOT optional

### 3.2 Transactional outbox schema (§5.11)
- Complete `outbox_messages` table with 16 columns (id, event_id, event_type, aggregate_type, aggregate_id, organisation_id, payload, payload_version, occurred_at, available_at, status, attempts, last_error, locked_at, locked_by, published_at, created_at)
- Unique event identifier for idempotency
- Indexes for pending selection, aggregate lookup, operational cleanup
- Full lifecycle: 9 steps from business transaction begin to permanent failure recovery
- At-least-once delivery semantics (NOT exactly-once)
- Worker crash recovery, concurrent worker coordination (`FOR UPDATE SKIP LOCKED`), retry backoff

### 3.3 Course-version referential integrity (§5.4.1)
- Composite unique key on `course_versions(id, course_id)`
- Composite foreign key from `courses(current_published_version_id, current_published_version_course_id)` to `course_versions(id, course_id)`
- Prevents a course from referencing a version belonging to a different course
- Migration order, publication transaction, archiving behaviour specified

### 3.4 Completion concurrency (§6.3.4 — substantially revised)
- Shared lock acquisition order: enrollment row FIRST, then progress rows
- Both `RecordProgressHandler` and `CompleteEnrollmentHandler` follow the same order
- Deadlock prevention via consistent ordering
- Bounded deadlock retry (3 retries, 10ms/50ms/100ms backoff)
- Eligibility revalidation within the locked transaction
- MariaDB 12.3 `innodb_snapshot_isolation=ON` behaviour noted
- Unique completion constraint as database-level idempotency guarantee

### 3.5 MariaDB compatibility assessment (§18b)
- 17-row compatibility table covering JSON, full-text, collation, generated columns, check constraints, transaction isolation, row locking, deadlock codes, index limits, UUID handling, DATETIME, TIMESTAMP range, DDL transactions, DBAL platform detection, SKIP LOCKED, composite FK
- 7 key takeaways documenting where engines differ and how the application handles each difference

### 3.6 Database-portable search (§10.1, ADR-025 revised)
- `CourseSearchInterface` abstraction in Application layer
- `MySQLFullTextSearchAdapter` and `MariaDBFullTextSearchAdapter` in Infrastructure
- Application does NOT promise identical search results across engines
- Different ranking algorithms, stopwords, minimum token sizes documented

### 3.7 Dual-database CI matrix (§11.4)
- GitHub Actions matrix: `database: [mysql-8.4, mariadb-12.3]`
- 13 pipeline steps per database
- Both engines must pass ALL gates

### 3.8 Enhanced integration test requirements (§14.2)
- Tests against BOTH MySQL 8.4 AND MariaDB 12.3
- JSON encoding/decoding/querying on both engines
- Full-text search on both engines
- Outbox insertion, delivery, retry, duplicate delivery
- UTC timestamp precision
- SQLite explicitly NOT acceptable as MySQL/MariaDB substitute

---

## 4. Preserved decisions

All 160 previously approved decisions (A/D/E/ADR series) remain preserved. 3 new DB decisions added. **Total: 163 approved decisions. Zero conflicts.**

See Deliverable C for the full traceability matrix.

---

## 5. Conflicts discovered

**NONE.** The three new DB decisions (DB-041/042/043) are consistent with all 160 existing approved decisions. No contradiction was found between:
- The new decisions and the existing architecture
- The corrected technical claims and the approved technology choices
- The MariaDB compatibility requirements and the existing schema design

---

*End of Deliverable B (revised).*
