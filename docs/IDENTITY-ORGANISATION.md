# Identity and Organisation (Gate 2B)

This document describes the Phase 2B identity and organisation subsystem: the
database schema, seeded RBAC catalogue, authentication and token flows, security
controls, organisation isolation rules, and the verification commands.

## Schema

The subsystem adds seven tables in migration `Version20261120000000` on top of
the Phase 1 `users` table. It does not modify any Phase 1 migration.

| Table | Purpose | Key columns and constraints |
|---|---|---|
| `users` | Phase 1 account record. | `id`, `email` (unique), `password_hash`, `status`, `failed_login_count`, `locked_until`, `last_login_at`, `created_at`, `updated_at`. |
| `user_profiles` | Display and locale preferences. | `id`, `user_id` (unique, FK to `users`), `display_name`, `preferred_tz`, `metadata` JSON, timestamps. |
| `roles` | Seed catalogue of role definitions. | `id`, `code` (unique), `name`, `description`, `created_at`. |
| `permissions` | Seed catalogue of permission codes. | `id`, `code` (unique), `description`, `created_at`. |
| `role_permissions` | Role → permission mapping. | Composite key `(role_id, permission_id)` with cascading FKs. |
| `user_roles` | Role assignments, global or organisation-scoped. | `id`, `user_id`, `role_id`, nullable `organisation_id`, nullable `assigned_by`, `assigned_at`. A `NULL` organisation means a global assignment. |
| `organisation_memberships` | Organisation membership lifecycle. | `id`, `organisation_id`, `user_id`, unique `(organisation_id, user_id)`, `status`, `joined_at`, `ended_at`, timestamps. |
| `user_tokens` | Single-use verification and reset tokens. | `id`, `user_id`, `purpose`, `token_hash` (unique), `expires_at`, `consumed_at`, `created_at`. |

Role and permission identifiers are fixed UUIDs so every environment shares the
same authorisation identity. Seeds use `INSERT IGNORE`, making the migration
safe to replay on a database that already contains the catalogue.

### Seed catalogue

Four roles:

| Code | Name |
|---|---|
| `platform:admin` | Platform Administrator |
| `lms:admin` | LMS Administrator |
| `lms:instructor` | Instructor |
| `lms:learner` | Learner |

Nineteen permissions:

| Permission |
|---|
| `course.create` |
| `course.update` |
| `course.publish` |
| `course.archive` |
| `enrollment.create` |
| `enrollment.cancel.self` |
| `enrollment.cancel.other` |
| `enrollment.view.self` |
| `enrollment.view.any` |
| `progress.record.self` |
| `progress.view.self` |
| `progress.view.any` |
| `organisation.members.view` |
| `organisation.members.manage` |
| `user.roles.assign` |
| `user.manage` |
| `audit.view` |
| `file.upload` |
| `file.download` |

Role → permission mapping:

| Role | Permissions |
|---|---|
| `platform:admin` | All 19 |
| `lms:admin` | All 19 |
| `lms:instructor` | `course.create`, `course.update`, `course.publish`, `course.archive`, `enrollment.view.any`, `progress.view.any`, `organisation.members.view`, `file.upload`, `file.download` (9) |
| `lms:learner` | `enrollment.create`, `enrollment.cancel.self`, `enrollment.view.self`, `progress.record.self`, `progress.view.self`, `file.download` (6) |

Registration assigns `lms:learner` globally in the same transaction that
creates the user, profile, and email-verification token. The assignment is
idempotent.

## Authentication flows

| Flow | Route | Behaviour |
|---|---|---|
| Register | `GET/POST /register` | Validates email and password policy, hashes the password, creates user/profile, assigns `lms:learner`, issues a 24h verification token, sends the verification mail, and records `user.registered`. |
| Verify email | `GET /verify/email/{token}` | Consumes the single-use verification token, moves the account from `pending_verification` to `active`, and records `user.email_verified`. |
| Login | `GET/POST /login` | Symfony form login with CSRF. Only `active` users may authenticate; pending, suspended, and deactivated users are rejected with a user-facing message. Successful login resets failure counters and stamps the session. |
| Logout | `GET /logout` | Invalidates the session and records `user.logged_out`. |
| Request reset | `GET/POST /reset-password` | Accepts an email, silently returns for unknown or non-active accounts, issues a 1h reset token, and sends the reset mail. |
| Reset password | `GET/POST /reset-password/{token}` | Consumes the single-use reset token, enforces the password policy, stores a new hash, and records `user.password_reset`. |
| Change password | `GET/POST /account/password` | Requires the current password, enforces the password policy, stores a new hash, invalidates the current session, and records `user.password_changed`. |
| Suspend | `SuspendUserHandler` | Requires `user.manage`, sets status `suspended`, records `user.suspended`. The session subscriber revokes active sessions on the next request. |
| Deactivate | `DeactivateUserHandler` | Requires `user.manage`, sets status `deactivated`, records `user.deactivated`. The session subscriber revokes active sessions on the next request. |

## Token model

- Only SHA-256 hashes are stored in `user_tokens.token_hash`. Raw tokens never
  touch the database, logs, or audit payloads.
- Tokens are single-use: consuming a token sets `consumed_at`, and a consumed
  token cannot be reused.
- Email verification tokens expire after 24 hours. Password reset tokens expire
  after 1 hour.
- Issuing a new token for the same user and purpose replaces the previous active
  token.
- Token lookup is by hash, protected by a unique index.

## Security controls

- **Firewall and sessions:** Symfony session firewall with a database-backed
  user provider. Public paths are login, registration, email verification,
  password reset, health, readiness, and static assets; everything else requires
  an authenticated user.
- **CSRF:** registration, login, password reset, password change, organisation
  creation, member addition, and membership ending all validate CSRF tokens.
  Form login uses Symfony's CSRF integration.
- **Login throttling:** `login_throttling` limits attempts to 5 per 15 minutes
  per client.
- **Account lockout:** five consecutive failed logins lock the account for 15
  minutes. Success resets the failure count and lock.
- **Session regeneration:** Symfony regenerates the session identifier on
  interactive login. Password changes invalidate the current session.
- **Session lifetime:** `gc_maxlifetime` is 1800 seconds of idle time. The
  session subscriber enforces an absolute maximum age of 8 hours (28800
  seconds) and rejects sessions without a login timestamp.
- **Session revocation:** each login stores the user's `updated_at` stamp in the
  session. A password change, suspension, or deactivation changes that stamp or
  the account status, and the next request revokes the session and redirects to
  login.
- **Error handling:** organisation controllers convert authorisation failures
  to HTTP 403 and missing organisations to HTTP 404. Validation and conflict
  errors become flash messages; internal exception details are not rendered.

## Administrative audit context

- Global-role administrative audit events carry `organisation_id = NULL`; this
  is intentional and distinct from organisation-scoped events.
- Platform-admin bootstrap and last-platform-admin revocation serialize on the
  `roles` row with `SELECT ... FOR UPDATE` before counting, so concurrent
  bootstrap/revocation attempts cannot create or remove the final administrator.
- Bootstrap audit rows are written inside the same transaction as the user,
  profile and role assignment, so a rolled-back bootstrap cannot leave a
  successful audit record.
- `platform:admin` is global-only; assigning it with an organisation scope is
  rejected.
- The last-platform-administrator guard counts only **active** users with the
  global role, so suspended/deactivated administrators do not satisfy it.
- Suspending or deactivating an active global `platform:admin` participates in
  the same role-row lock and refuses to remove the last active administrator.
- Suspension and deactivation always take the `platform:admin` row lock as the
  first statement of their transaction, so a role granted concurrently to the
  target is observed by the last-administrator count instead of racing it.
- Revoking a role from an **inactive** platform administrator is allowed when
  another active administrator remains; the guard only applies to active
  targets.
- Reactivation clears failed-login and lockout state for suspended, deactivated
  or active-but-locked accounts; reactivating an already-active unlocked account
  is a no-op and writes no duplicate audit record.
- Admin UI templates never render password hashes or authentication tokens.

## RBAC and organisation isolation

- Roles can be global (`organisation_id IS NULL`) or organisation-scoped.
- `PermissionChecker` asks `RoleRepositoryInterface` for permissions. With a
  scope, it grants permissions from both global assignments and assignments for
  that organisation. It never grants permissions from another organisation.
- `lms:learner` is assigned globally at registration.
- Creating an organisation requires the global `user.manage` permission and
  creates an active membership for the creator.
- Viewing or managing members requires `organisation.members.view` or
  `organisation.members.manage` in that organisation's scope.
- Organisation listing is membership-based: `GET /organisations` returns only
  organisations where the current user has an active membership.
- Cross-organisation access is denied by the permission checker. Functional
  tests cover a learner requesting another organisation's members (403) and an
  organisation-scoped administrator listing only their own organisation.

## Presentation routes

| Route | Name | Method | Purpose |
|---|---|---|---|
| `/organisations` | `app_organisations` | GET | List the current user's organisations and show the create form. |
| `/organisations` | `app_organisation_create` | POST | Create an organisation (CSRF `create_organisation`). |
| `/organisations/{organisationId}/members` | `app_organisation_members` | GET | List organisation members. |
| `/organisations/{organisationId}/members` | `app_organisation_member_add` | POST | Add a member by user id (CSRF `add_membership`). |
| `/organisations/{organisationId}/members/{userId}/end` | `app_organisation_member_end` | POST | End a membership (CSRF `end_membership`). |

Templates are under `templates/organisation/` and extend `base.html.twig`. They
use accessible labels, plain forms, and local CSS only; no inline JavaScript.

## Verification

Run these commands from the repository root:

```bash
composer validate --strict --no-check-publish
php bin/console lint:yaml config --parse-tags
php bin/console lint:container --env=dev
php bin/console lint:container --env=test
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite architecture
vendor/bin/phpstan analyse --no-progress --memory-limit=512M
vendor/bin/php-cs-fixer fix --dry-run --diff
```

Database-backed suites need `DATABASE_URL` pointing at a real MySQL 8.4 or
MariaDB instance:

```bash
DATABASE_URL="mysql://root@127.0.0.1:3306/lms_test?charset=utf8mb4" \
    php bin/console doctrine:migrations:migrate --no-interaction
DATABASE_URL="mysql://root@127.0.0.1:3306/lms_test?charset=utf8mb4" \
    vendor/bin/phpunit --testsuite integration
DATABASE_URL="mysql://root@127.0.0.1:3306/lms_test?charset=utf8mb4" \
    vendor/bin/phpunit --testsuite functional
```

Both engines are mandatory targets. MySQL 8.4 and MariaDB 12.3 are first-class;
SQLite is not acceptable. CI runs the database suites against both engines.

The functional suite includes `OrganisationAccessTest`, which covers learner
access, 403 responses for unauthorised org creation and member listing,
platform-admin organisation creation with creator membership, member management,
cross-organisation isolation, and CSRF rejection without row creation.
