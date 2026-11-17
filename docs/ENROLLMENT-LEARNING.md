# Enrollment and Learning (Phase 2D)

This document covers the enrollment and learning-progress vertical added in
Phase 2D: schema, version binding, lifecycle, progress transitions, completion
rules, transactional outbox writes, authorisation and presentation routes.

## Schema

Migration `Version20261201000000` appends four tables. It does not alter Phase 1
(`users`, `organisations`, `courses`, `course_versions`), Phase 2B
(`roles`, `permissions`, `user_roles`, memberships, tokens) or Phase 2C
(`course_modules`, `lessons`, `lesson_contents`, categories, instructors).

| Table | Purpose | Key constraints |
|---|---|---|
| `enrollments` | A learner's enrollment in one course version | `UNIQUE (learner_user_id, course_id, _active_flag)`, FKs to organisations, users, courses and course versions (`ON DELETE RESTRICT`) |
| `module_progress` | Aggregated progress for a module inside an enrollment | `UNIQUE (enrollment_id, course_module_id)`, FK to `enrollments` (`ON DELETE CASCADE`) |
| `lesson_progress` | Per-lesson progress with optimistic `version` and optional JSON data | `UNIQUE (enrollment_id, lesson_id)`, FK to `enrollments` (`ON DELETE CASCADE`) |
| `course_completions` | Immutable completion record for a finished enrollment | `UNIQUE (enrollment_id)`, FKs to enrollments, users, courses and course versions |

### Generated active flag

`enrollments._active_flag` is a generated virtual column:

```sql
CASE WHEN status = 'active' THEN 1 ELSE NULL END
```

The unique key `uq_active_enrollment (learner_user_id, course_id,
_active_flag)` therefore permits **one active enrollment** per learner and
course while allowing any number of historical `cancelled` and `completed`
rows: non-active rows write `NULL`, and MySQL/MariaDB unique indexes ignore
`NULL` values.

### Version binding

An enrollment stores both `course_id` and `course_version_id`. The version is
resolved from `courses.current_published_version_id` at enrollment time. Course
content is versioned and immutable once published, so an enrollment keeps
access to the content that was published when the learner enrolled, even after
a newer version is published.

Re-enrollment after cancelling inserts a **new row** bound to the current
published version. Historical cancelled/completed rows are never reused or
deleted.

## Lifecycle

```text
                 enroll
                   │
                   ▼
                active ──complete──▶ completed
                   │
                   └──cancel──────▶ cancelled

cancelled / completed ──enroll (new row)──▶ active
```

Domain rules:

- `Enrollment::complete()` and `Enrollment::cancel()` are only valid from
  `active`; other transitions raise `InvalidEnrollmentTransitionException`.
- Completed and cancelled enrollments are terminal. Re-enrollment creates a new
  active row.
- Enrollment requires the course to have a current published version; otherwise
  `CourseNotPublishedException` is raised.

## Progress rules

- Lesson progress supports `not_started`, `in_progress` and `completed`.
- Transitions are monotonic: `not_started → in_progress → completed`. A
  backwards transition (`completed → in_progress`) raises
  `ProgressConflictException`.
- Repeating the current status is idempotent: the recorder returns without
  writing, so timestamps, versions and row counts do not change.
- Module progress is derived from the published lesson set of the module, not
  from recorded rows alone. A module becomes `completed` only when that set is
  non-empty and every lesson has a recorded `completed` progress row. Any
  recorded `in_progress` or `completed` lesson keeps the module `in_progress`
  until all lessons are completed. A module with zero lessons never becomes
  `completed`; it has no module progress row.
- `lesson_progress` and `module_progress` carry a `version` column for
  optimistic locking. Updates use `WHERE id = ? AND version = ?`; a stale write
  raises `ProgressConflictException::versionMismatch`.
- `RecordLessonProgressHandler` retries a conflicting transaction up to three
  times with short backoff. The retry count is deliberately capped so divergent
  clients receive a conflict instead of spinning.
- Lock order: the handler locks the enrollment row (`SELECT ... FOR UPDATE`)
  before reading/writing progress. Completion also locks the enrollment first
  and then reads progress and completions, preventing lock inversion.

### Completion

`CompleteEnrollmentHandler`:

1. Locks the enrollment and authorises the actor.
2. Returns the existing completion immediately when the enrollment is already
   `completed` (idempotent retry).
3. Rejects non-active enrollments.
4. Rejects a course version with **no required modules** (`CannotCompleteEmptyCourseException`).
5. Rejects any incomplete required module (`IncompleteModulesException`).
6. Inserts exactly one `course_completions` row (the unique key on
   `enrollment_id` is the backstop) and marks the enrollment `completed`.

Optional modules are ignored for completion. Lessons in a required module must
exist and be completed; an empty required-module course can never complete.

## Outbox writer

`DbalOutboxWriter` is **insert-only** and participates in the caller's DBAL
transaction. Enrollment handlers run inside
`TransactionManagerInterface::transactional()`, so the aggregate write, audit
event and outbox row commit or roll back together.

Event payloads are intentionally minimal and contain no credentials:

| Event | Payload |
|---|---|
| `EnrollmentCreated` | `enrollment_id`, `learner_user_id`, `course_id`, `course_version_id` |
| `EnrollmentCancelled` | `enrollment_id`, `learner_user_id`, `course_id`, `cancelled_at` |
| `EnrollmentCompleted` | `enrollment_id`, `learner_user_id`, `course_id`, `course_version_id`, `completion_id` |

No relay worker is implemented in this gate. Rows remain `pending` for a future
outbox dispatcher; the insert path uses `ON DUPLICATE KEY UPDATE id = id` so an
event id can be retried safely.

## Authorisation and organisation isolation

`EnrollmentAccessPolicy` centralises the rules:

- Learners may view and manage only their own enrollments.
- `enrollment.view.any` (organisation-scoped) permits managers to view.
- `user.manage` (organisation-scoped) permits administrative completion.
- Creating a self-enrollment requires `enrollment.create`; an administrator
  enrolling another learner requires `user.manage`.
- Self-enrollment additionally requires an **active organisation membership**
  unless the actor holds `user.manage` for that organisation.

Access to another learner's enrollment is rejected by the policy and presented
as HTTP 403. Unknown enrollments are presented as HTTP 404.

## HTTP routes

| Method | Path | Route | CSRF token |
|---|---|---|---|
| GET | `/enrollments` | `app_enrollments` | — |
| POST | `/courses/{courseId}/enroll` | `app_enroll_course` | `enroll_course` |
| GET | `/enrollments/{enrollmentId}` | `app_enrollment_show` | — |
| POST | `/enrollments/{enrollmentId}/lessons/{lessonId}/progress` | `app_lesson_progress` | `lesson_progress` |
| POST | `/enrollments/{enrollmentId}/complete` | `app_complete_enrollment` | `complete_enrollment` |
| POST | `/enrollments/{enrollmentId}/cancel` | `app_cancel_enrollment` | `cancel_enrollment` |

Invalid or missing CSRF tokens return HTTP 400 before any handler runs.
Forbidden access raises HTTP 403; domain, validation and conflict errors become
a flash message and a redirect, never an internal error page.

Templates `templates/enrollment/index.html.twig` and
`templates/enrollment/show.html.twig` render only titles, statuses, dates and
progress. They never expose password hashes, tokens or other credentials.

## Tests

```bash
# Unit + architecture
composer test

# Static analysis and style
vendor/bin/phpstan analyse --no-progress --memory-limit=512M
vendor/bin/php-cs-fixer fix --dry-run --diff

# Integration and functional suites against a real database
APP_ENV=test DATABASE_URL='mysql://…/lms_test?charset=utf8mb4' php bin/console doctrine:migrations:migrate --no-interaction
APP_ENV=test DATABASE_URL='mysql://…/lms_test?charset=utf8mb4' vendor/bin/phpunit --testsuite integration
APP_ENV=test DATABASE_URL='mysql://…/lms_test?charset=utf8mb4' vendor/bin/phpunit --testsuite functional
```

Both **MySQL 8.4** and **MariaDB 12.3** are required targets. Integration and
functional tests must pass against both engines; SQLite is not supported.
