# Deliverable E — Outstanding Questions (revised)

Only genuine unresolved decisions requiring user input. Previously resolved decisions are NOT listed.

---

## Resolved decisions (NOT asked again)

The following were outstanding in the prior version and are now resolved by approved decisions:

| Prior question | Resolution | Decision |
|---|---|---|
| MySQL target version | MySQL 8.4 LTS | DB-041 |
| MariaDB support | MariaDB 12.3 LTS (first-class) | DB-043 |
| UUID generation package | Symfony UID (`symfony/uid`) | DB-042 |

---

## Remaining outstanding questions

### Question 1 — PHP extension list

**Context:** The application requires specific PHP extensions. The exact list depends on implementation choices.

**Technical recommendation:** `ext-mbstring`, `ext-pdo` + `ext-pdo_mysql`, `ext-intl`, `ext-json`, `ext-zlib`, `ext-curl`, `ext-opcache` (recommended).

**Blocks Phase 1?** NO (can start with minimal set, add as needed).

### Question 2 — Staging environment hosting

**Context:** Staging hosting affects CI/CD pipeline design, cost, environment parity.

**Options:** Cloud VM / PaaS / Self-hosted / Docker Compose on VM.

**Technical recommendation:** Cloud VM matching production infrastructure.

**Blocks Phase 1?** NO (Phase 8 concern).

### Question 3 — Email delivery provider

**Context:** Symfony Mailer supports multiple providers (SMTP, SendGrid, Postmark, Amazon SES).

**Technical recommendation:** Defer to Phase 6; provider with webhook support for delivery tracking.

**Blocks Phase 1?** NO.

### Question 4 — Repository licensing

**Context:** Public GitHub repository requires licence selection.

**Options:** MIT / Apache-2.0 / GPL-3.0 / Proprietary.

**Technical recommendation:** Apache-2.0 (matches DGLab). Legal decision.

**Blocks Phase 1?** NO (Phase 9 concern).

### Question 5 — Production hosting location

**Context:** Nigeria Data Protection Act may affect data sovereignty.

**Technical recommendation:** Defer to Phase 8 privacy review.

**Blocks Phase 1?** NO.

### Question 6 — Initial organisation seed

**Context:** First installation needs an initial organisation + administrator.

**Technical recommendation:** Setup wizard or CLI command at Phase 9.

**Blocks Phase 1?** NO.

---

## Summary

| # | Question | Phase | Blocks Phase 1? |
|---|---|---|---|
| 1 | PHP extension list | Phase 1 | NO |
| 2 | Staging hosting | Phase 8 | NO |
| 3 | Email provider | Phase 6 | NO |
| 4 | Repository licence | Phase 9 | NO |
| 5 | Production hosting location | Phase 8 | NO |
| 6 | Initial organisation seed | Phase 9 | NO |

**None block Phase 1.** Three prior questions (MySQL version, MariaDB, UUID package) are now resolved by DB-041/042/043 and are NOT asked again.

---

*End of Deliverable E (revised).*
