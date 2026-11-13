# Deliverable C — Decision Traceability Matrix (revised)

All 163 approved decisions mapped to their implementing SPEC-001 v2.1 (revised) section.

**Status legend:** PRESERVED / CLARIFIED / CONFLICT (none found)

**Summary:**

| Category | Total | Preserved | Clarified | Conflict |
|---|---:|---:|---:|---:|
| A-001–A-040 | 40 | 37 | 3 | 0 |
| D-001–D-040 | 40 | 36 | 4 | 0 |
| E-001–E-040 | 40 | 39 | 1 | 0 |
| ADR-001–ADR-040 | 40 | 36 | 4 | 0 |
| DB-041–DB-043 | 3 | 3 | 0 | 0 |
| **Total** | **163** | **151** | **12** | **0** |

---

## Part 5: New Database Decisions (DB-041 to DB-043)

| ID | Approved decision | SPEC v2.1 section | Status |
|---|---|---|---|
| DB-041 | Primary database: MySQL 8.4 LTS | §3.1 (Database support matrix), §20 | PRESERVED |
| DB-042 | UUID generation: Symfony UID (`symfony/uid`), UUIDv7, CHAR(36), application-layer generation | §3.1 (DB-042), §20.5, ADR-004 | PRESERVED |
| DB-043 | First-class MariaDB 12.3 LTS support; both engines mandatory CI targets | §3.1 (DB-043), §18b (MariaDB Compatibility), §11.4 (CI matrix), §14.2 (Integration tests) | PRESERVED |

---

## Cross-reference to prior matrix parts

- **Part 1: A-001–A-040** — 40 decisions, all preserved/clarified (see prior version for full table; no changes in this revision)
- **Part 2: D-001–D-040** — 40 decisions, all preserved/clarified (see prior version; no changes)
- **Part 3: E-001–E-040** — 40 decisions, all preserved/clarified (see prior version; no changes)
- **Part 4: ADR-001–ADR-040** — 40 decisions. ADR-025 (Search) updated from "MySQL full-text" to "database-portable search interface" (CLARIFIED). ADR-001 (Symfony version) retains corrected facts. ADR-003 (DBAL) retains PHP ^8.2 correction. ADR-024 (Messenger) retains DBAL-only clarification.

---

## Zero conflicts

**Zero conflicts discovered.** All 163 approved decisions are preserved. 12 decisions received clarification. No decision was silently deleted, weakened, or reinterpreted. The 3 new DB decisions are fully consistent with the existing 160.

---

*End of Deliverable C (revised).*
