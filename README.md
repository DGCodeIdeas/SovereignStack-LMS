# SovereignStack LMS

**Standalone Learning Management System** — an independent, production-ready LMS extracted from [DGLab](https://github.com/DGCodeIdeas/DGLab).

## Status

**Architecture approved; implementation pending explicit authorisation.**

- **Specification version:** 2.1 (revised)
- **Architecture decisions:** 163 approved (A-001–A-040, D-001–D-040, E-001–E-040, ADR-001–ADR-040, DB-041–DB-043)
- **Source project:** DGLab at commit `543329ed84a2148308e0cc455ac7e2fe27cf35f4`

## Technology stack

| Layer | Technology |
|---|---|
| Language | PHP 8.4 |
| Framework | Symfony 7.4 LTS |
| Architecture | Modular Monolith |
| Primary database | MySQL 8.4 LTS |
| Alternative database | MariaDB 12.3 LTS (first-class supported) |
| Database access | Doctrine DBAL 4.5.x |
| Migrations | Doctrine Migrations |
| UUID generation | Symfony UID (`symfony/uid`), UUIDv7 |
| Rich-text editor | HugeRTE (MIT-licensed) |
| Frontend | Twig + Bootstrap 5.3 |
| CI/CD | GitHub Actions |

## Documentation

The complete architecture specification and supporting deliverables are in [`docs/`](docs/):

- [`docs/SPEC-001-v2.1.md`](docs/SPEC-001-v2.1.md) — Complete architecture specification (160 + 3 decisions)
- [`docs/DELIVERABLE-B-CHANGE-SUMMARY.md`](docs/DELIVERABLE-B-CHANGE-SUMMARY.md) — Change summary
- [`docs/DELIVERABLE-C-TRACEABILITY-MATRIX.md`](docs/DELIVERABLE-C-TRACEABILITY-MATRIX.md) — Decision traceability matrix
- [`docs/DELIVERABLE-D-PHASE1-READINESS.md`](docs/DELIVERABLE-D-PHASE1-READINESS.md) — Phase 1 readiness report
- [`docs/DELIVERABLE-E-OUTSTANDING-QUESTIONS.md`](docs/DELIVERABLE-E-OUTSTANDING-QUESTIONS.md) — Outstanding questions

## Extraction strategy

**Copy, not move.** The LMS implementation in DGLab becomes a frozen reference copy after extraction. This repository becomes the authoritative implementation. There is no ongoing two-way synchronisation between the repositories.

## License

Apache-2.0. See [`LICENSE`](LICENSE).

## Repository governance

- Protected `main` branch (to be configured)
- Reviewed pull requests required
- All 163 architectural decisions are the approved baseline
- Implementation requires explicit authorisation per phase
