# Local Database Tuning — Development Baseline

**Scope:** local development only. This document does not change production configuration.

## Engine and host

| Item | Value |
|---|---|
| Engine | MariaDB `11.8.6-MariaDB-5ubuntu0.1` |
| Host | 4 vCPU, 11 GiB RAM (~2.2 GiB available at capture), 31 GiB swap (~4.1 GiB used) |
| Disk | 105 GiB available on `/`; `/tmp` tmpfs 5.8 GiB |
| Access | Dedicated `DBOps` account with full privileges on this local instance |
| Shared databases | `lms`, `lms_test`, `dglab_test`, `phpmyadmin` plus system schemas |
| Storage engines | All LMS tables are InnoDB. No MyISAM/Aria tables exist in `lms` or `lms_test`. |

The server is shared with `dglab_test` and `phpmyadmin`, so global server-variable changes were not applied without evidence and owner approval.

## Baseline configuration

| Variable | Baseline |
|---|---|
| `innodb_buffer_pool_size` | 128 MiB |
| `innodb_buffer_pool_instances` | 1 |
| `key_buffer_size` | 128 MiB |
| `table_open_cache` | 2000 |
| `table_definition_cache` | 400 |
| `tmp_table_size` / `max_heap_table_size` | 16 MiB / 16 MiB |
| `max_connections` | 151 |
| `thread_cache_size` | 151 |
| `open_files_limit` | 32186 |
| `innodb_file_per_table` | ON |
| `innodb_flush_log_at_trx_commit` | 1 |
| `innodb_io_capacity` | 200 |
| `query_cache_type` / `query_cache_size` | OFF / 1 MiB (left OFF) |
| `slow_query_log` | OFF |
| `long_query_time` | 10 s |

## Baseline status and warning triage

| Counter / ratio | Baseline | Assessment |
|---|---|---|
| InnoDB buffer-pool hit ratio | 99.96% (459 reads / 1,162,785 requests) | Healthy; no buffer-pool pressure |
| Table-open cache hit ratio | 86.4% (30,764 hits / 4,869 misses) | Acceptable; test schema churn lowers short-window ratio |
| `Slow_queries` | 0 | No slow queries recorded |
| `Created_tmp_tables` / disk | 7,802 / 1,334 (17.1%) | Mostly test-harness and small sorts; no evidence of production disk-temp pressure |
| `Select_full_join` | 287 | No LMS query shape uses a full join; historical counter includes other/test activity |
| `Sort_merge_passes` | 0 | No disk-backed sort merges |
| `Aborted_connects` / clients | 26 / 0 | Credential failures (`dgci`, `root`, `lms` login attempts), not dropped LMS connections |
| Query cache | OFF | Correct for MariaDB/MySQL 8-era deployments; left OFF |

The warnings about “many rows sorted”, “joins without indexes”, “temporary tables on disk”, “high index/table reads”, “table opening rate”, and “aborted connections” were not reproduced as LMS query problems. They are generic/global or test-harness artifacts. The genuine local issues were the disabled slow-query log and the 10-second threshold; both were corrected.

## Query-plan review

`EXPLAIN` and MariaDB `ANALYZE FORMAT=JSON` were run for the LMS hot paths with real `lms_test` parameters:

- users by email: unique index, `const`
- user roles/permissions (global and org-scoped): user-role index → role/permission primary keys
- organisations by slug / list-for-user: unique slug and membership index
- courses by organisation and org+slug: `uq_courses_org_slug` / `idx_courses_org`
- course versions by course and draft-by-course/status: `uq_cv_course_version`
- modules by version ordered by position: `uq_cm_version_position`
- lessons by module ordered by position: `uq_lessons_module_position`
- lesson contents by lesson: unique lesson key
- instructors by course+user: `idx_instr_course` + small in-memory sort

All plans were index-driven. No `type=ALL`, no large row scans, no disk temporary tables on the hot paths. Measured plan times were sub-millisecond (for example permissions ≈0.28 ms, courses-by-org ≈0.06 ms, draft version ≈0.08 ms). Small `filesort` operations remain for presentation ordering by `code`, `name`, or `assigned_at`; they are negligible at current cardinality.

**No index was added.** There was no query-plan evidence of a missing index. Optional composite indexes such as `user_roles(user_id, organisation_id)` and `course_instructors(course_id, user_id)` may be considered only if future data volume/profiling shows a real bottleneck; they should ship as a Doctrine migration at that time.

## Changes applied locally

1. Enabled slow-query logging at runtime:
   - `slow_query_log = ON`
   - `long_query_time = 1` second
   - log file: `/tmp/mariadb-slow.log`
   - `log_queries_not_using_indexes = OFF` (avoid noise)
2. Refreshed optimizer statistics:
   - `ANALYZE TABLE` on all 23 `lms` tables and all 23 `lms_test` tables — 46/46 returned `OK`.
3. Took logical backups before any diagnostic work:
   - `/tmp/db-tuning/lms-20261115165023.sql` (41,119 bytes)
   - `/tmp/db-tuning/lms_test-20261115165023.sql` (39,995 bytes)
   - Both dumps completed successfully with `--single-transaction --routines --triggers --no-tablespaces`.

No schema changes, no index changes, no global memory changes, no query-cache enablement, no service restarts, and no changes to `dglab_test` or `phpmyadmin`.

## Measurements after the changes

### Slow queries

- `Slow_queries` remained **0** after the full integration and functional suites and 80 endpoint requests.
- Slow log contains headers only; no query entries.
- The log file is owned by `mysql:mysql` mode 660, so the `dgci` shell cannot read it directly; it is readable through MariaDB. The `Slow_queries` counter is the authoritative check here.

### Workload and query plans

- Integration suite: **102 tests / 392 assertions**, 20.796 s.
- Functional suite: **30 tests / 689 assertions**, 34.390 s.
- Buffer-pool hit ratio after workload: **99.965%** (526 reads / 1,506,524 requests).
- `Sort_merge_passes`: 0.
- Test-window disk temporary tables: 52 of 487 new temp tables (10.7%), dominated by test schema creation/drop churn rather than LMS steady-state SQL.
- Test-window table-cache hit ratio fell to 73.2% because the suite creates/drops many tables; cumulative ratio is ~85%.
- `Select_full_join` increased by 8 during the test window; no LMS `EXPLAIN` shape uses a full join. Historical counters cannot be attributed per database.
- `Aborted_connects` stayed at 26; `Aborted_clients` 0. The 26 are failed local login attempts, not LMS connection drops.

### HTTP latency (20 requests per endpoint)

| Endpoint | Codes | Average | p95 | Max |
|---|---:|---:|---:|---:|
| `/health` | 20 × 200 | 0.0147 s | 0.0180 s | 0.0196 s |
| `/ready` | 20 × 200 | 0.0161 s | 0.0199 s | 0.0219 s |
| `/login` | 20 × 200 | 0.0165 s | 0.0208 s | 0.0236 s |
| `/courses` | 20 × 302 | 0.0140 s | 0.0152 s | 0.0157 s |

All endpoints were fast and stable; `/courses` correctly redirects unauthenticated requests.

## Production recommendations (not applied here)

These are separate from the local development instance and require owner approval, capacity review, and a maintenance window/restart:

1. **Buffer pool:** 128 MiB is small for a database host, but the LMS dataset is ~1.5 MiB and the hit ratio is effectively 100%. Increase to 1–2 GiB only after confirming host memory availability; the current host has ~2.2 GiB available and significant swap usage, so raising it blindly risks memory pressure.
2. **Key buffer:** 128 MiB is wasted for an all-InnoDB workload. Reducing to 16–32 MiB could reclaim memory, but it is a global variable affecting other applications.
3. **Temporary tables:** 16 MiB is adequate for the observed workload. Raise only if production profiling shows a sustained disk-temp ratio from LMS queries.
4. **Table definition cache:** 400 is adequate for the current table count. Review if the schema grows substantially.
5. **Slow-query log:** retain in production with rotation/retention and a 1–2 s threshold; monitor `*_slow.log` or `mysql.slow_log`.
6. **Query cache:** keep OFF. MySQL 8 removed it; MariaDB’s query cache is not a substitute for proper indexes.
7. **Connection handling:** the app uses DBAL with short-lived requests. The observed aborted connections are credential failures; verify deployment credentials and avoid exposing the `DBOps` account outside local development.
8. **Indexes:** add only from measured production `EXPLAIN`/slow-log evidence, via an append-only Doctrine migration. Do not add speculative indexes.

## Local vs production

- **Local:** slow log ON, 1-second threshold, `ANALYZE TABLE`, backups, no schema/index/global memory changes. This is safe for a shared development host and is reversible by `SET GLOBAL slow_query_log=OFF`.
- **Production:** apply only the measured, owner-approved items above; keep all global changes separate from application migrations and document them in the deployment runbook.

## Restore

Logical backups can be restored with:

```bash
MYSQL_PWD='<password>' mysql -h 127.0.0.1 -P 3306 -u DBOps lms < /tmp/db-tuning/lms-20261115165023.sql
MYSQL_PWD='<password>' mysql -h 127.0.0.1 -P 3306 -u DBOps lms_test < /tmp/db-tuning/lms_test-20261115165023.sql
```

Do not restore over a production database without a reviewed recovery plan.