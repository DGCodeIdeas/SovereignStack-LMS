# Shared Infrastructure (Phase 2A)

This document describes the shared infrastructure available to every module in
SovereignStack LMS. Modules build on these contracts; they do not re-implement
persistence, error handling, request correlation, or file storage.

No module in this layer depends on Doctrine ORM. Database access is **Doctrine
DBAL only**.

## Components

| Component | Location | Purpose |
|---|---|---|
| Transaction manager | `src/Shared/Application/Transaction/TransactionManagerInterface.php`, `src/Shared/Infrastructure/Persistence/DbalTransactionManager.php` | Runs a unit of work atomically and propagates rollbacks. |
| Result / Error types | `src/Shared/Domain/Result/Result.php`, `Error.php`, `ErrorCode.php` | Explicit success/failure return values for domain and application code. |
| Exception hierarchy | `src/Shared/Domain/Exception/DomainException.php`, `src/Shared/Application/Exception/*` | Typed failures mapped to stable HTTP problem responses. |
| Problem responses | `src/Shared/Presentation/Http/ProblemDetailsFactory.php`, `ProblemResponseListener.php` | RFC 7807 responses with no internal detail leakage. |
| Correlation IDs | `src/Shared/Infrastructure/Correlation/CorrelationIdHolder.php`, `CorrelationIdSubscriber.php` | Per-request request ID accepted, generated, propagated, and logged. |
| Log processor | `src/Shared/Infrastructure/Logging/CorrelationIdProcessor.php` | Adds `correlation_id` to every Monolog record while a request is active. |
| File storage | `src/Shared/Application/Storage/FileStorageInterface.php`, `src/Shared/Infrastructure/Storage/LocalFileStorage.php` | Private local storage with opaque keys and content validation. |
| DB-aware functional base | `tests/Functional/DatabaseWebTestCase.php` | Boots the kernel, migrates once per process, and exposes a DBAL connection. |

## Transaction semantics

`TransactionManagerInterface::transactional(callable $operation)` is the only
supported transaction boundary. `DbalTransactionManager` implements it directly
on the Doctrine DBAL `Connection`:

- **Commit:** the callable's return value is returned after `commit()`.
- **Rollback:** any `\Throwable` from the callable triggers `rollBack()` (when a
  transaction is still active) and the original exception is re-thrown unchanged.
- **Nested calls:** DBAL savepoints provide nesting. An inner failure rolls back
  to the inner savepoint and re-throws; the outer transaction may catch it and
  continue, or let it propagate and roll back everything.
- **No partial commits:** a caller never observes a half-applied unit of work.

Application and domain code must not call `beginTransaction()`/`commit()`
directly. The manager is aliased from `TransactionManagerInterface` and marked
public for tests.

## Error and status mapping

`ProblemDetailsFactory` maps failures to HTTP status codes and problem types.
`ProblemResponseListener` writes the response for every uncaught kernel
exception.

| Failure | Status | Problem type | Detail |
|---|---|---|---|
| `DomainException` | 422 | `domain-violation` | Exception message |
| `ApplicationException` | 400 | `application-error` | Exception message |
| `ValidationException` | 400 | `validation-failed` | Exception message, plus `errors` |
| `NotFoundException` | 404 | `not-found` | Exception message |
| `ConflictException` | 409 | `conflict` | Exception message |
| `ForbiddenException` | 403 | `forbidden` | Exception message |
| `HttpExceptionInterface` | its status | `http-{status}` | Fixed, status-specific safe text |
| Any other `\Throwable` | 500 | `internal-error` | Exactly `An unexpected error occurred.` |

Problem types are namespaced under `https://sovereignstack.dev/problems/`.

### RFC 7807 example

```json
{
  "type": "https://sovereignstack.dev/problems/validation-failed",
  "title": "Validation failed",
  "status": 400,
  "detail": "Validation failed.",
  "instance": "/api/v1/courses",
  "correlation_id": "0192f3a4-5b6c-7d8e-9f01-23456789abcd",
  "errors": {
    "email": ["Email is invalid."]
  }
}
```

Responses use `Content-Type: application/problem+json; charset=utf-8`.
`instance` is the request path only; the query string is excluded.

### No-leak rule

For unexpected failures the response never includes the exception class, file,
line, stack trace, previous exception, SQL, DSN, or raw message. The detail is
always the fixed sentence above. 4xx HTTP exceptions likewise use fixed safe
text instead of the raw exception message. 5xx failures are logged at `error`
with the exception and correlation ID; 4xx failures are logged at `warning`
with the correlation ID, status, path, and a safe error code/message.

## Correlation IDs

The `X-Correlation-ID` header is accepted on the main request only:

- A supplied value is reused when it matches
  `^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$`.
- A missing or invalid value is replaced with a generated UUIDv7.
- The value is stored as the `_correlation_id` request attribute and in
  `CorrelationIdHolder`.
- The response always carries `X-Correlation-ID`.
- The holder is cleared on kernel terminate.

`CorrelationIdProcessor` (tagged `monolog.processor`) adds `correlation_id` to
log records, so application logs can be joined to the HTTP response.

## File storage

`FileStorageInterface` is the module-facing contract. `LocalFileStorage` stores
files under the private root `var/storage`, which is outside the public web
root.

- **Opaque keys:** `YYYY/MM/<uuidv7>.<ext>`, partitioned by UTC year and month.
  Physical paths are never returned to callers.
- **Allowlist:** only PDF, PNG/JPEG/GIF/WebP, plain text, DOCX/PPTX/XLSX, and MP4.
- **Extension/content match:** the original filename extension must match the
  canonical extension for the declared MIME type.
- **Magic-byte/MIME check:** `finfo` inspects the bytes and they must match the
  declared type (Office documents are accepted as ZIP containers).
- **Size limit:** 10 MiB by default (`app.file_storage_max_bytes`).
- **Executables rejected:** executable extensions are not in the allowlist.
- **Traversal containment:** separators, null bytes, and `..` are rejected, and
  resolved paths must stay inside the real storage root.

Failures surface as `ValidationException`/`NotFoundException` and therefore map
to the problem responses above.

## Testing

| Command | Scope |
|---|---|
| `vendor/bin/phpunit --testsuite unit` | Pure unit tests |
| `vendor/bin/phpunit --testsuite architecture` | Dependency and boundary rules |
| `vendor/bin/phpunit --testsuite integration` | DBAL/migrations (requires `DATABASE_URL`) |
| `vendor/bin/phpunit --testsuite functional` | HTTP kernel + problem responses (requires `DATABASE_URL`) |

Both **MySQL 8.4** and **MariaDB 12.3** are mandatory targets. CI runs the
integration and functional suites against both engines in a matrix; neither
engine may be skipped. SQLite is not an acceptable substitute.

The test-only probe route `/_test/problem/{type}` is loaded from
`config/routes/test/` and registered through `config/services_test.yaml`, so it
exists only in the `test` environment and is absent from `dev` and `prod`.
