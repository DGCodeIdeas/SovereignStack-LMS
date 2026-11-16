# Course Content (Phase 2C)

This document covers the course authoring vertical added in Phase 2C: schema,
lifecycle, immutability, ordering, organisation ownership, HTML sanitisation and
the presentation routes. Enrollment, learning progress and learner-facing
delivery are deliberately **out of scope** for this gate (Phase 2D).

## Schema additions

Migration `Version20261125000000` appends six tables and one trigger. It does
not alter Phase 1 `courses` / `course_versions` columns or constraints.

| Table | Purpose | Key constraints |
|---|---|---|
| `course_modules` | Ordered modules inside a course version | `UNIQUE (course_version_id, position)`, FK to `course_versions` (`ON DELETE CASCADE`) |
| `lessons` | Ordered lessons inside a module | `UNIQUE (course_module_id, position)`, FK to `course_modules` (`ON DELETE CASCADE`) |
| `lesson_contents` | Sanitised HTML plus JSON metadata per lesson | `UNIQUE (lesson_id)`, FK to `lessons` (`ON DELETE CASCADE`) |
| `course_categories` | Organisation-scoped categories | `UNIQUE (organisation_id, slug)`, FK to `organisations` |
| `course_category_assignments` | Many-to-many course/category links | Composite PK `(course_id, category_id)` |
| `course_instructors` | Instructor assignments with effective window | FKs to `courses` and `users` (`ON DELETE RESTRICT`) |

Phase 1 remains authoritative for `courses` (organisation, owner, slug,
visibility, current published version pair) and `course_versions`
(version number, title, description, status, published_at, created_by).

## Lifecycle state machine

```text
draft ──submit──▶ review ──publish──▶ published ──archive──▶ archived
  ▲                 │
  └────reject───────┘

published / archived ──new draft──▶ draft (next version number)
```

Rules enforced by the domain:

- Only `draft` versions accept content mutations (module/lesson/content edits,
  course metadata updates).
- `submit` requires at least one module; module titles must be non-empty.
- `publish` is allowed only from `review`, is restricted to administrators, and
  every **required** module must contain at least one lesson with content.
- `reject` returns a `review` version to `draft` and is administrator-only.
- `archive` moves a `published` version to `archived` and clears both published
  reference columns on the course.
- Creating a new draft requires the latest version to be `published` or
  `archived`, and only one draft may exist at a time.

## Immutability

Published and archived versions are immutable in two layers:

1. **Database trigger** `trg_course_versions_prevent_content_update` rejects
   updates that change `course_id`, `version_number`, `title`, `description` or
   `created_by`. Lifecycle-only changes (`status`, `published_at`) remain
   permitted so publishing and archiving can complete.
2. **Application layer** `CourseVersion::assertEditable()` plus
   `CourseVersionResolver::requireDraft()` ensure every content handler targets
   the draft. After publishing, content routes fail with a flash message and the
   stored rows are unchanged.

The presentation layer renders courses in any state, but mutations against a
non-draft version are rejected by the same rules.

## Ordering strategy

Modules and lessons use dense integer positions starting at zero, guarded by a
unique `(parent, position)` index. Reordering is a two-phase operation in one
transaction:

1. Every row of the parent moves to a unique negative position
   (`position = -position - 1`).
2. Rows receive their final zero-based positions in the requested order.

This avoids transient unique-key collisions when an order is reversed. Handlers
also verify that the submitted id list is exactly the current child set with no
duplicates.

## Organisation ownership and authorisation

- A course belongs to exactly one organisation (`courses.organisation_id`) and
  one owner (`courses.owner_user_id`).
- `CourseAccessPolicy` grants edit to the owner, an assigned instructor, or an
  administrator holding `user.manage` scoped to the course organisation.
- Creating a course requires `course.create` scoped to the target organisation.
- Listing requires `course.update` or `user.manage` scoped to the organisation.
- Publishing, rejecting and archiving require `user.manage` scoped to the
  organisation. The seeded `lms:instructor` role therefore cannot publish or
  archive, as covered by the functional tests.

The presentation layer resolves the current user's organisations through the
`Organisation\Application\Contract\UserOrganisationsInterface` contract. The
course module exposes an equivalent `Course\Application\Contract` facade so a
course controller never imports the organisation module directly (architecture
boundary). The controller lists each organisation the user belongs to, asks
`ListCoursesHandler` per organisation, skips organisations where listing is
forbidden, merges results and de-duplicates by course id.

## HTML sanitisation

`SymfonyHtmlSanitizer` stores only allow-listed structural, textual and media
elements (`h1`–`h6`, `p`, lists, tables, links, images, code blocks). Script,
style, iframe, object and embed elements are dropped. URL schemes are limited to
`http`, `https` and `mailto`, event handlers and inline styles are removed, and
`app.html_sanitizer_force_https` upgrades links/media to HTTPS. Sanitisation
happens in the application layer before `LessonContent` is persisted.

## Presentation routes

| Route | Method | Name | CSRF token |
|---|---|---|---|
| `/courses` | GET | `app_courses` | — |
| `/courses/new` | GET | `app_course_new` | — |
| `/courses` | POST | `app_course_create` | `create_course` |
| `/courses/{courseId}` | GET | `app_course_edit` | — |
| `/courses/{courseId}` | POST | `app_course_update` | `update_course` |
| `/courses/{courseId}/modules` | POST | `app_course_module_add` | `add_module` |
| `/courses/{courseId}/modules/{moduleId}/delete` | POST | `app_course_module_remove` | `remove_module` |
| `/courses/{courseId}/modules/reorder` | POST | `app_course_modules_reorder` | `reorder_modules` |
| `/courses/{courseId}/modules/{moduleId}/lessons` | POST | `app_course_lesson_add` | `add_lesson` |
| `/courses/{courseId}/modules/{moduleId}/lessons/{lessonId}/delete` | POST | `app_course_lesson_remove` | `remove_lesson` |
| `/courses/{courseId}/modules/{moduleId}/lessons/reorder` | POST | `app_course_lessons_reorder` | `reorder_lessons` |
| `/courses/{courseId}/lessons/{lessonId}/content` | POST | `app_course_lesson_content` | `save_content` |
| `/courses/{courseId}/submit` | POST | `app_course_submit` | `submit_review` |
| `/courses/{courseId}/reject` | POST | `app_course_reject` | `reject_review` |
| `/courses/{courseId}/publish` | POST | `app_course_publish` | `publish_course` |
| `/courses/{courseId}/archive` | POST | `app_course_archive` | `archive_course` |
| `/courses/{courseId}/draft` | POST | `app_course_new_draft` | `new_draft` |

All mutations derive the actor from `DomainUserAwareInterface`. Forbidden
operations return `403`; validation, conflict and domain errors become flash
messages followed by a redirect. Invalid CSRF tokens return `400`. Templates are
server-rendered, use accessible labels, and contain no inline JavaScript.
Reorder controls post the complete ordered id list from simple up/down buttons.

### HugeRTE follow-up

Lesson authoring currently uses a plain `<textarea>` for sanitised HTML. HugeRTE
(MIT) must be integrated later as a **self-hosted** asset with pinned versions
and integrity hashes. It is **not** integrated in Gate 2C.

## Test commands

Both MySQL 8.4 and MariaDB 12.3 are mandatory targets; run the integration and
functional suites against both engines.

```bash
composer validate --strict --no-check-publish
php bin/console lint:yaml config --parse-tags
php bin/console lint:container --env=dev
php bin/console lint:container --env=test
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite architecture
vendor/bin/phpstan analyse --no-progress --memory-limit=512M
vendor/bin/php-cs-fixer fix --dry-run --diff

APP_ENV=test DATABASE_URL="$DATABASE_URL" php bin/console doctrine:migrations:migrate --no-interaction
APP_ENV=test DATABASE_URL="$DATABASE_URL" vendor/bin/phpunit --testsuite integration
APP_ENV=test DATABASE_URL="$DATABASE_URL" vendor/bin/phpunit --testsuite functional
```

`tests/Functional/CourseContentFlowTest.php` covers creation, module/lesson
ordering, sanitisation, review/publish/archive, published immutability,
cross-organisation isolation, instructor restrictions and CSRF rejection.
