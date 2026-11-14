# Extraction Manifest — SovereignStack-LMS from DGLab

**Source repository:** DGLab (`https://github.com/DGCodeIdeas/DGLab`)  
**Source baseline SHA:** `543329ed84a2148308e0cc455ac7e2fe27cf35f4` (verified 2026-11-14)  
**Target repository:** SovereignStack-LMS (`https://github.com/DGCodeIdeas/SovereignStack-LMS`)  
**Extraction strategy:** Copy-not-move (DGLab LMS remains intact as frozen reference)  
**Date:** 2026-11-14  

> **Baseline note:** The baseline SHA was verified on 2026-11-14 against `git ls-remote` and the local DGLab `main` HEAD. An earlier handover reported `25d4e963…`; that SHA does not exist on the current shared `main`. The verified SHA `543329ed84a2148308e0cc455ac7e2fe27cf35f4` supersedes it.

---

## 1. Source Inventory (DGLab `Spokes/Internal/Lms/`)

### 1.1 Domain Entities (4 files)
| Source Path | Description | Target Module | Disposition |
|---|---|---|---|
| `Spokes/Internal/Lms/Domain/Entity/Course.php` | Course aggregate root with state machine | Course | **Adapt** — rename namespace, replace `CourseId` with UUIDv7, remove DGLab dependencies |
| `Spokes/Internal/Lms/Domain/Entity/Module.php` | Module entity | Course | **Adapt** — rename namespace, replace `ModuleId` with UUIDv7 |
| `Spokes/Internal/Lms/Domain/Entity/Enrollment.php` | Enrollment aggregate root | Enrollment | **Adapt** — rename namespace, replace IDs with UUIDv7 |
| `Spokes/Internal/Lms/Domain/Entity/Progress.php` | Progress entity | Learning | **Adapt** — rename namespace, replace IDs with UUIDv7 |

### 1.2 Domain Value Objects (7 files)
| Source Path | Description | Target Module | Disposition |
|---|---|---|---|
| `Spokes/Internal/Lms/Domain/ValueObject/CourseId.php` | 26-char hex ID wrapper | Course | **Replace** — new `CourseId` using UUIDv7 (`Symfony\Component\Uid\UuidV7`) |
| `Spokes/Internal/Lms/Domain/ValueObject/CourseSlug.php` | Slug value object | Course | **Adapt** — rename namespace |
| `Spokes/Internal/Lms/Domain/ValueObject/CourseTitle.php` | Title value object | Course | **Adapt** — rename namespace |
| `Spokes/Internal/Lms/Domain/ValueObject/ModuleId.php` | 26-char hex ID wrapper | Course | **Replace** — new `ModuleId` using UUIDv7 |
| `Spokes/Internal/Lms/Domain/ValueObject/EnrollmentId.php` | 26-char hex ID wrapper | Enrollment | **Replace** — new `EnrollmentId` using UUIDv7 |
| `Spokes/Internal/Lms/Domain/ValueObject/EnrollmentStatus.php` | Status enum | Enrollment | **Adapt** — rename namespace |
| `Spokes/Internal/Lms/Domain/ValueObject/ModuleStatus.php` | Status enum | Course | **Adapt** — rename namespace |

**Note:** `ProgressId.php` does not exist in the inspected source. The earlier count of eight value objects was incorrect; seven exist.

### 1.3 Domain Exceptions (4 files)
| Source Path | Description | Target Module | Disposition |
|---|---|---|---|
| `Spokes/Internal/Lms/Domain/Exception/CourseNotFoundException.php` | Not found exception | Course | **Adapt** — rename namespace |
| `Spokes/Internal/Lms/Domain/Exception/EnrollmentNotFoundException.php` | Not found exception | Enrollment | **Adapt** — rename namespace |
| `Spokes/Internal/Lms/Domain/Exception/AlreadyEnrolledException.php` | Business rule exception | Enrollment | **Adapt** — rename namespace |
| `Spokes/Internal/Lms/Domain/Exception/CannotRevertCompletedModuleException.php` | Business rule exception | Learning | **Adapt** — rename namespace |

**Note:** `InvalidCourseTransitionException.php` does not exist in the inspected source. The earlier count of five exceptions was incorrect; four exist.

### 1.4 Domain Repository Interfaces (4 files)
| Source Path | Description | Target Module | Disposition |
|---|---|---|---|
| `Spokes/Internal/Lms/Domain/Repository/CourseRepositoryInterface.php` | Course persistence contract | Course | **Adapt** — rename namespace, update to DBAL |
| `Spokes/Internal/Lms/Domain/Repository/ModuleRepositoryInterface.php` | Module persistence contract | Course | **Adapt** — rename namespace, update to DBAL |
| `Spokes/Internal/Lms/Domain/Repository/EnrollmentRepositoryInterface.php` | Enrollment persistence contract | Enrollment | **Adapt** — rename namespace, update to DBAL |
| `Spokes/Internal/Lms/Domain/Repository/ProgressRepositoryInterface.php` | Progress persistence contract | Learning | **Adapt** — rename namespace, update to DBAL |

### 1.5 Application Commands (8 files)
| Source Path | Description | Target Module | Disposition |
|---|---|---|---|
| `Spokes/Internal/Lms/Application/Command/CreateCourseCommand.php` | Create course DTO | Course | **Adapt** — rename namespace |
| `Spokes/Internal/Lms/Application/Command/UpdateCourseCommand.php` | Update course DTO | Course | **Adapt** — rename namespace |
| `Spokes/Internal/Lms/Application/Command/PublishCourseCommand.php` | Publish course DTO | Course | **Adapt** — rename namespace |
| `Spokes/Internal/Lms/Application/Command/ArchiveCourseCommand.php` | Archive course DTO | Course | **Adapt** — rename namespace |
| `Spokes/Internal/Lms/Application/Command/AddModuleCommand.php` | Add module DTO | Course | **Adapt** — rename namespace |
| `Spokes/Internal/Lms/Application/Command/EnrollCommand.php` | Enroll learner DTO | Enrollment | **Adapt** — rename namespace |
| `Spokes/Internal/Lms/Application/Command/CancelEnrollmentCommand.php` | Cancel enrollment DTO | Enrollment | **Adapt** — rename namespace |
| `Spokes/Internal/Lms/Application/Command/RecordProgressCommand.php` | Record progress DTO | Learning | **Adapt** — rename namespace |

### 1.6 Application Services (3 files)
| Source Path | Description | Target Module | Disposition |
|---|---|---|---|
| `Spokes/Internal/Lms/Application/Service/CourseApplicationService.php` | Course use cases | Course | **Rewrite** — split into Handler/QueryHandler per CQRS, remove DGLab deps |
| `Spokes/Internal/Lms/Application/Service/EnrollmentApplicationService.php` | Enrollment use cases | Enrollment | **Rewrite** — split into Handler/QueryHandler per CQRS |
| `Spokes/Internal/Lms/Application/Service/ProgressApplicationService.php` | Progress use cases | Learning | **Rewrite** — split into Handler/QueryHandler per CQRS |

### 1.7 Application Interfaces (3 files)
| Source Path | Description | Target Module | Disposition |
|---|---|---|---|
| `Spokes/Internal/Lms/Application/EnrollmentApplicationInterface.php` | Public contract | Enrollment | **Adapt** — rename namespace, refine for CQRS |
| `Spokes/Internal/Lms/Application/ProgressApplicationInterface.php` | Public contract | Learning | **Adapt** — rename namespace, refine for CQRS |
| `Spokes/Internal/Lms/Application/CourseApplicationInterface.php` | Public contract | Course | **Adapt** — rename namespace, refine for CQRS |

### 1.8 Infrastructure Repositories (4 files)
| Source Path | Description | Target Module | Disposition |
|---|---|---|---|
| `Spokes/Internal/Lms/Infrastructure/Persistence/MySQLCourseRepository.php` | PDO-based course repo | Course | **Rewrite** — use Doctrine DBAL, UUIDv7, new schema |
| `Spokes/Internal/Lms/Infrastructure/Persistence/MySQLModuleRepository.php` | PDO-based module repo | Course | **Rewrite** — use Doctrine DBAL, UUIDv7, new schema |
| `Spokes/Internal/Lms/Infrastructure/Persistence/MySQLEnrollmentRepository.php` | PDO-based enrollment repo | Enrollment | **Rewrite** — use Doctrine DBAL, UUIDv7, new schema |
| `Spokes/Internal/Lms/Infrastructure/Persistence/MySQLProgressRepository.php` | PDO-based progress repo | Learning | **Rewrite** — use Doctrine DBAL, UUIDv7, new schema |

### 1.9 Tests (7 files)
| Source Path | Description | Target Module | Disposition |
|---|---|---|---|
| `Spokes/Internal/Lms/tests/Unit/Domain/CourseTest.php` | Course domain tests | Course | **Adapt** — rename namespace, update assertions |
| `Spokes/Internal/Lms/tests/Unit/Application/CourseApplicationServiceModuleTest.php` | Course service tests | Course | **Rewrite** — split into Handler/QueryHandler tests |
| `Spokes/Internal/Lms/tests/Unit/Application/CourseApplicationServicePublishedCoursesTest.php` | Published courses tests | Course | **Rewrite** — split into Handler/QueryHandler tests |
| `Spokes/Internal/Lms/tests/Unit/Application/EnrollmentApplicationServiceTest.php` | Enrollment service tests | Enrollment | **Rewrite** — split into Handler/QueryHandler tests |
| `Spokes/Internal/Lms/tests/Unit/Infrastructure/MySQLCourseRepositoryTest.php` | Course repo integration tests | Course | **Rewrite** — DBAL + real MySQL/MariaDB |
| `Spokes/Internal/Lms/tests/Unit/Infrastructure/MySQLEnrollmentRepositoryTest.php` | Enrollment repo integration tests | Enrollment | **Rewrite** — DBAL + real MySQL/MariaDB |
| `Spokes/Internal/Lms/tests/Unit/Infrastructure/MySQLModuleRepositoryTest.php` | Module repo integration tests | Course | **Rewrite** — DBAL + real MySQL/MariaDB |

**Note:** 7 test files contain 67 test methods in total.

### 1.10 Migrations (9 files)
| Source Path | Description | Disposition |
|---|---|---|
| `Spokes/Internal/Lms/migrations/001_create_courses_table.sql` | Courses table (CHAR(26) IDs) | **Preserve as provenance** — document in `docs/extraction/migration-history.md` |
| `Spokes/Internal/Lms/migrations/002_create_modules_table.sql` | Modules table | **Preserve as provenance** |
| `Spokes/Internal/Lms/migrations/003_create_enrollments_table.sql` | Enrollments table | **Preserve as provenance** |
| `Spokes/Internal/Lms/migrations/004_create_progress_table.sql` | Progress table | **Preserve as provenance** |
| `Spokes/Internal/Lms/migrations/005_add_module_content.sql` | Module content columns | **Preserve as provenance** |
| `Spokes/Internal/Lms/migrations/006_create_course_versions_table.sql` | Course versions table | **Preserve as provenance** |
| `Spokes/Internal/Lms/migrations/007_add_course_version_column.sql` | Course version column | **Preserve as provenance** |
| `Spokes/Internal/Lms/migrations/008_enrollment_add_columns.sql` | Enrollment columns | **Preserve as provenance** |
| `Spokes/Internal/Lms/migrations/009_enrollment_indexes_fk.sql` | Enrollment indexes/FKs | **Preserve as provenance** |

**Note:** The nine migrations are uniquely numbered `001`–`009`. The earlier claim that two migrations share prefix `006` is incorrect: the actual pair is `006_create_course_versions_table.sql` and `007_add_course_version_column.sql`.

### 1.11 Controllers (4 files, outside `Spokes/Internal/Lms/`)
| Source Path | Description | Disposition |
|---|---|---|
| `app/Controller/Lms/CourseController.php` | Course HTTP endpoints | **Rewrite** — Symfony controllers, CQRS handlers |
| `app/Controller/Lms/EnrollmentController.php` | Enrollment HTTP endpoints | **Rewrite** — Symfony controllers, CQRS handlers |
| `app/Controller/Lms/ModuleController.php` | Module HTTP endpoints | **Rewrite** — Symfony controllers, CQRS handlers |
| `app/Controller/Lms/ProgressController.php` | Progress HTTP endpoints | **Rewrite** — Symfony controllers, CQRS handlers |

### 1.12 Additional Files
| Source Path | Description | Disposition |
|---|---|---|
| `Products/LMS/Bootstrap.php` | Placeholder bootstrap | **Omit** — replaced by Symfony bootstrap |
| `CLI/migrate.php` | Migration runner (repository root) | **Omit** — replaced by Doctrine Migrations |
| `CLI/seed.php` | Seeder, broken with hardcoded creds (repository root) | **Omit** — rewrite properly in Phase 2+ |

**Note:** The four controllers live in `app/Controller/Lms/`, not under `Spokes/Internal/Lms/`. The two CLI scripts live at the DGLab repository root (`CLI/migrate.php`, `CLI/seed.php`), not under the LMS directory.

---

## 2. DGLab Contracts to Eliminate/Replace

| DGLab Contract | Location | Replacement Strategy |
|---|---|---|
| `SovereignStack\Core\Database\ConnectionInterface` | Core/Database/ConnectionInterface.php | Replace with `Doctrine\DBAL\Connection` |
| `SovereignStack\Hub\Identity\Application\IdentityInterface` | Hub/Identity/Application/IdentityInterface.php | Replace with Symfony Security + own `UserRepositoryInterface` |
| `SovereignStack\Hub\Identity\Domain\ValueObject\UserId` | Hub/Identity/Domain/ValueObject/UserId.php | Replace with own `UserId` (UUIDv7) in Identity module |
| `SovereignStack\Hub\Identity\Domain\ValueObject\RoleIdentifier` | Hub/Identity/Domain/ValueObject/RoleIdentifier.php | Replace with own `RoleIdentifier` in Identity module |

---

## 3. Target Module Structure (SovereignStack-LMS)

```
src/
├── Shared/
│   ├── Domain/
│   │   ├── ValueObject/ (UuidV7Id, etc.)
│   │   └── Exception/
│   ├── Application/
│   │   └── TransactionManager/
│   └── Infrastructure/
│       └── Persistence/ (DBAL connection, base repository)
│
├── Identity/
│   ├── Domain/
│   │   ├── Entity/User.php
│   │   ├── ValueObject/{UserId, Email, RoleIdentifier, PasswordHash}
│   │   ├── Exception/
│   │   └── Repository/UserRepositoryInterface.php
│   ├── Application/
│   │   ├── Command/ (RegisterUser, VerifyEmail, ResetPassword, etc.)
│   │   ├── Query/ (GetUser, FindByEmail, etc.)
│   │   ├── Handler/
│   │   ├── QueryHandler/
│   │   ├── Contract/ (UserApplicationInterface)
│   │   └── DTO/
│   ├── Infrastructure/
│   │   └── Persistence/MySQLUserRepository.php
│   └── Presentation/
│       ├── Controller/
│       └── Form/
│
├── Organisation/
│   ├── Domain/
│   │   ├── Entity/{Organisation, Membership}
│   │   ├── ValueObject/{OrganisationId, MembershipId, MembershipRole}
│   │   ├── Exception/
│   │   └── Repository/{OrganisationRepositoryInterface, MembershipRepositoryInterface}
│   ├── Application/
│   │   ├── Command/ (CreateOrganisation, AddMember, etc.)
│   │   ├── Query/
│   │   ├── Handler/
│   │   ├── QueryHandler/
│   │   ├── Contract/
│   │   └── DTO/
│   ├── Infrastructure/
│   │   └── Persistence/{MySQLOrganisationRepository, MySQLMembershipRepository}.php
│   └── Presentation/
│
├── Course/
│   ├── Domain/
│   │   ├── Entity/{Course, CourseVersion, Module, Lesson, LessonContent}
│   │   ├── ValueObject/{CourseId, CourseSlug, CourseTitle, ModuleId, LessonId, CourseVisibility}
│   │   ├── Event/
│   │   ├── Exception/
│   │   └── Repository/{CourseRepositoryInterface, CourseVersionRepositoryInterface, ModuleRepositoryInterface}
│   ├── Application/
│   │   ├── Command/ (CreateCourse, UpdateCourse, SubmitForReview, PublishCourse, ArchiveCourse, AddModule, RemoveModule)
│   │   ├── Query/ (ListPublishedCourses, GetCourseDetail, GetCourseVersion)
│   │   ├── Handler/
│   │   ├── QueryHandler/
│   │   ├── Contract/CourseApplicationInterface.php
│   │   └── DTO/{CourseSummaryDTO, CourseDetailDTO, CourseVersionDTO}
│   ├── Infrastructure/
│   │   └── Persistence/{MySQLCourseRepository, MySQLCourseVersionRepository, MySQLModuleRepository}.php
│   └── Presentation/
│       ├── Controller/CourseController.php
│       ├── Form/{CourseFormType, ModuleFormType}
│       └── View/course/
│
├── Enrollment/
│   ├── Domain/
│   │   ├── Entity/Enrollment
│   │   ├── ValueObject/{EnrollmentId, EnrollmentStatus}
│   │   ├── Exception/
│   │   └── Repository/EnrollmentRepositoryInterface.php
│   ├── Application/
│   │   ├── Command/{EnrollCommand, CancelEnrollmentCommand, ReEnrollCommand}
│   │   ├── Query/{GetEnrollment, ListEnrollments}
│   │   ├── Handler/
│   │   ├── QueryHandler/
│   │   ├── Contract/EnrollmentApplicationInterface.php
│   │   └── DTO/{EnrollmentDTO, EnrollmentSummaryDTO}
│   ├── Infrastructure/
│   │   └── Persistence/MySQLEnrollmentRepository.php
│   └── Presentation/
│
├── Learning/
│   ├── Domain/
│   │   ├── Entity/{Progress, CourseCompletion}
│   │   ├── ValueObject/{ProgressId, LessonProgressStatus}
│   │   ├── Exception/
│   │   └── Repository/{ProgressRepositoryInterface, CourseCompletionRepositoryInterface}
│   ├── Application/
│   │   ├── Command/{RecordProgressCommand, CompleteModuleCommand, CompleteCourseCommand}
│   │   ├── Query/{GetProgress, GetCompletion}
│   │   ├── Handler/
│   │   ├── QueryHandler/
│   │   ├── Contract/LearningApplicationInterface.php
│   │   └── DTO/{ProgressDTO, CompletionDTO}
│   ├── Infrastructure/
│   │   └── Persistence/{MySQLProgressRepository, MySQLCourseCompletionRepository}.php
│   └── Presentation/
│
└── Administration/
    ├── Application/
    │   ├── Query/ (Reporting queries)
    │   ├── QueryHandler/
    │   └── Contract/
    ├── Infrastructure/
    │   └── Persistence/ (Reporting repositories)
    └── Presentation/
        └── Controller/
```

---

## 4. Schema Mapping (DGLab → SovereignStack-LMS)

| DGLab Table | DGLab ID Type | SovereignStack Table | SovereignStack ID Type | Notes |
|---|---|---|---|---|
| `lms_courses` | CHAR(26) | `courses` | CHAR(36) UUIDv7 | Add `organisation_id`, `owner_user_id`, `current_published_version_id` |
| `lms_modules` | CHAR(26) | `course_modules` | CHAR(36) UUIDv7 | Add `course_version_id` FK, `is_required` default true |
| `lms_enrollments` | CHAR(26) | `enrollments` | CHAR(36) UUIDv7 | Add `course_version_id` FK, `organisation_id` |
| `lms_progress` | CHAR(26) | `module_progress` | CHAR(36) UUIDv7 | Add `enrollment_id` FK, optimistic locking `version` |
| (new) | — | `course_versions` | CHAR(36) UUIDv7 | Immutable published versions |
| (new) | — | `lessons` | CHAR(36) UUIDv7 | Per module |
| (new) | — | `lesson_contents` | CHAR(36) UUIDv7 | Rich text content |
| (new) | — | `course_completions` | CHAR(36) UUIDv7 | Historical completion records |
| (new) | — | `organisations` | CHAR(36) UUIDv7 | Multi-tenant isolation |
| (new) | — | `memberships` | CHAR(36) UUIDv7 | User-org relationships |
| (new) | — | `users` | CHAR(36) UUIDv7 | Independent auth |
| (new) | — | `messenger_messages` | BIGINT AUTO_INCREMENT | Symfony Messenger transport |
| (new) | — | `outbox_messages` | CHAR(36) UUIDv7 | Transactional outbox |

---

## 5. Known Defects in Source (to Correct)

1. **CLI/seed.php** — Structural problems, hardcoded credentials → **Omit, rewrite properly**
2. **CourseApplicationService::isCourseComplete()** — TODO stub returning false → **Implement properly in Learning module**
3. **26-char hex IDs** — Not UUIDv7 → **Replace all with UUIDv7 (CHAR(36))**
4. **Products/LMS/Bootstrap.php** — Placeholder only → **Omit, use Symfony bootstrap**
5. **OpenAPI document** — No OpenAPI document was found inside the inspected LMS directories; this is recorded as **unverified**. A repository-root `openapi.yaml` exists in DGLab and describes LMS endpoints, but its ownership and currency were not verified. → **Generate/verify in Phase 4+**

---

## 6. Phase 1 Status (Current)

- [x] Symfony 7.4 / PHP 8.4 foundation
- [x] DBAL 4.x (no ORM)
- [x] Doctrine Migrations
- [x] outbox index corrected to `KEY idx_outbox_pending (status, available_at)`
- [x] course-version composite FK implemented in migration
- [x] Messenger DBAL prototype tests (CI evidence pending)
- [x] CI matrix MySQL 8.4 + MariaDB 12.3 (CI evidence pending)
- [ ] Business module extraction (out of Phase 1 scope)

**Status wording:** items above are implemented in the repository; test and CI outcomes are **CI evidence pending** and are not claimed as passing until the orchestrator supplies results.

**Out of scope for Phase 1:** business domain modules (Identity, Organisation, Course, Enrollment, Learning, Administration), authentication implementation, repository implementations, and business schema migrations beyond the foundation schema.

---

## 7. Verification Checklist

- [ ] Source SHA recorded: `543329ed84a2148308e0cc455ac7e2fe27cf35f4`
- [ ] All 44 PHP files inventoried
- [ ] All 9 SQL migrations inventoried
- [ ] 4 DGLab contracts identified for elimination
- [ ] Target module structure defined
- [ ] Schema mapping documented
- [ ] Known defects catalogued
- [ ] Phase 1 scope confirmed

---

*This manifest is a living document. Update as extraction progresses.*