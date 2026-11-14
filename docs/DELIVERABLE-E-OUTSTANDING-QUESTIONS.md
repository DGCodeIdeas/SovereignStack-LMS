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
| PHP extension list | Minimal runtime declared; CI adds the full set | Phase 1 |
| Repository licensing | Apache-2.0 | Already approved |

---

## Remaining outstanding questions

### Question 2 — Staging environment hosting

**Context:** Staging hosting affects CI/CD pipeline design, cost, environment parity.

**Options:** Cloud VM / PaaS / Self-hosted / Docker Compose on VM.

**Technical recommendation:** Cloud VM matching production infrastructure.

**Blocks Phase 1?** NO (Phase 8 concern).

### Question 3 — Email delivery provider

**Context:** Symfony Mailer supports multiple providers (SMTP, SendGrid, Postmark, Amazon SES).

**Technical recommendation:** Defer to Phase 6; provider with webhook support for delivery tracking.

**Blocks Phase 1?** NO.

### Question 5 — Production hosting location

**Context:** Nigeria Data Protection Act may affect data sovereignty.

**Technical recommendation:** Defer to Phase 8 privacy review.

**Blocks Phase 1?** NO.

### Question 6 — Initial organisation seed

**Context:** First installation needs an initial organisation + administrator.

**Technical recommendation:** Setup wizard or CLI command at Phase 9.

**Blocks Phase 1?** NO.

---

## Resolved during Phase 1

### Question 1 — PHP extension list — RESOLVED

**Resolution:** Phase 1 requires `ext-pdo`, `ext-pdo_mysql`, `ext-json`, and `ext-mbstring` (declared in `composer.json`). CI additionally installs `intl`, `bcmath`, `xml`, `ctype`, `iconv`, `curl`, `sodium`, and `zip`. The minimal local runtime is sufficient for the Phase 1 foundation; no further user decision is required.

### Question 4 — Repository licensing — RESOLVED

**Resolution:** Apache-2.0 is approved by the user and already committed in `LICENSE`, `composer.json`, and `README.md`. No reconfirmation is required.

---

## Summary

| # | Question | Phase | Blocks Phase 1? |
|---|---|---|---|
| 1 | PHP extension list — RESOLVED | Phase 1 | NO |
| 2 | Staging hosting | Phase 8 | NO |
| 3 | Email provider | Phase 6 | NO |
| 4 | Repository licence — RESOLVED (Apache-2.0) | Phase 9 | NO |
| 5 | Production hosting location | Phase 8 | NO |
| 6 | Initial organisation seed | Phase 9 | NO |

**None block Phase 1.** Q1 and Q4 are resolved; three earlier questions (MySQL version, MariaDB, UUID package) are resolved by DB-041/042/043 and are NOT asked again.

---

*End of Deliverable E (revised).*
