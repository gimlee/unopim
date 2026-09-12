## Context

See `proposal.md` for motivation. The current product route returns a 705 KB HTML shell and then requests DataGrid JSON. The JSON request currently fails during paginator count because `ProductDataGrid` always joins `product_listing_histories` while its tracked migration is pending. The shared DataGrid clears `isLoading` only in its success branch. The active Windows service is the single-process PHP built-in server, and its PHP SAPI has no OPcache loaded.

The current database is small: 252 products and 40 top-level configurable products. A reduced product query completes below 2 ms, while framework startup and request handling dominate. The existing repository already includes an Nginx/PHP-FPM development stack, PHP-FPM pool settings, production OPcache settings, Laravel Octane as a dependency, database queues, and tests around listing history. Docker, IIS, RoadRunner, and FrankenPHP executables are not currently available on the machine.

## Goals / Non-Goals

**Goals:**

- Restore correct product-list behavior and make any future list failure visible and recoverable.
- Remove listing-history work from the product count/base pagination query and load it once per page.
- Reduce global request noise and prevent long operations from consuming all web capacity.
- Supply one supported repository-owned multi-worker runtime profile and reproducible before/after measurements.
- Preserve PostgreSQL and MySQL compatibility already claimed by the product query layer.

**Non-Goals:**

- Installing Docker, IIS, WSL, RoadRunner, or another machine-wide service automatically.
- Enabling Elasticsearch or Redis without measurements that require them.
- Adding speculative indexes before the complete successful query has been measured at representative data volume.
- Redesigning the UnoPIM admin UI or changing product-list response fields and routes.
- Deploying to remote Sites, committing, or pushing changes.

## Decisions

### 1. Apply the existing migration before changing query behavior

Run migration status and a targeted `--pretend`, then apply the existing listing-history migration through Artisan so the migrations table remains authoritative. Run the other pending migration only if its ordering or feature dependency requires it; do not create the table from an ad-hoc SQL script.

Alternative considered: make the query silently ignore a missing table. Rejected because it hides a broken deployment state and would make listing status disappear without an operator-visible fault. The improved DataGrid error state provides a recoverable UI while schema readiness remains an operational invariant.

### 2. Hydrate latest listing history after base pagination

Remove the history join and correlated latest-row subquery from the base product query. After the paginator selects the current page, issue one batch query for those product IDs using a window rank ordered by `COALESCE(completed_at, started_at, created_at) DESC, id DESC`, then attach the latest record before formatting closures run. The query returns no row for products without history.

This keeps product count and sorting independent from history volume and prevents duplicate rows. A per-product query was rejected because it produces N+1 behavior. A permanent latest-history pointer was rejected for now because it adds write-path consistency and migration complexity that the current volume does not justify. If cross-database window syntax cannot be expressed safely, use one ordered query for the page IDs and group the first row in application memory, capped by a documented maximum per page; validate its behavior with high-history fixtures.

### 3. Give DataGrid requests ownership and cancellation

Track a monotonically increasing request generation and one abort controller in the shared DataGrid. Starting a new load aborts the prior load. Success and error handlers first check ownership; only the owning request can update records, errors, or loading. A 15-second list timeout is applied per request. The component stores a sanitized error state and renders an inline retry action while retaining filters and prior successful rows where possible.

Alternative considered: add only `.finally(() => isLoading=false)`. Rejected because an older request could close the loading state or overwrite results belonging to a newer filter request.

### 4. Replace fixed notification intervals with a self-scheduling poll

Use one self-scheduling timer that starts the next poll only after the prior request settles. Skip scheduling while `document.hidden` is true, refresh immediately on `visibilitychange` to visible, and exponentially back off failures up to a bounded interval. Clear listeners and timers during unmount. This avoids overlap without changing the notification API.

Cross-tab leader election was considered but deferred because hidden-tab suspension and overlap protection address the measured waste with much less coordination state. It can be added later if telemetry shows many simultaneously visible tabs.

### 5. Use the existing Nginx/PHP-FPM stack as the supported concurrent profile

Avoid a custom Windows proxy or unsupported PHP built-in worker setting. Enable OPcache in the development FPM profile with timestamp validation and a zero/short revalidation interval, retain multiple PHP-FPM children, and ensure Nginx serves built assets and public media directly with compression and cache headers appropriate to hashed files.

Add concise local documentation and prerequisite checks. On this machine, where Docker is absent, validate syntax/configuration and the repository's temporary OPcache benchmark but explicitly report that the multi-worker target cannot be executed until the documented prerequisite is installed. Do not silently alter the global `E:\php-8.4.25-nts-Win32-vs17-x64\php.ini`.

IIS/FastCGI was considered because it supports native Windows process pools, but maintaining a second full server configuration would duplicate the existing container stack. Laravel Octane/RoadRunner was considered but its server executable and Windows runtime requirements are absent, and adopting it would require a separate long-lived-worker compatibility audit.

### 6. Separate external work from acceptance

For listing launch, the downstream endpoint already represents asynchronous scheduling, so use a short configurable connect/acceptance timeout rather than the complete 300-second automation timeout. Preserve the returned attempt identity and existing status polling.

For image translation, add a dedicated queued job and persistent status record or reuse the existing translation record if it can represent queued/running/failed states without ambiguity. The submit endpoint validates inputs, creates the task state, dispatches to a dedicated queue, and returns HTTP 202 plus a status identifier. The worker performs the existing translation and persistence logic. Keep the existing synchronous service logic callable by the job to avoid duplicating translation behavior.

### 7. Make service configuration cache-safe

Define one canonical PIM service configuration with base URL, connect timeout, acceptance timeout, and long-running worker timeout. Controllers read only `config(...)`; environment access remains inside configuration files. Add a test that warms configuration cache in a subprocess or validates the cached repository to prevent endpoint drift.

### 8. Treat indexes as evidence-driven follow-up

After migration and batch hydration, capture the full successful product-list queries and `EXPLAIN (ANALYZE, BUFFERS)` for default and name sorting. Add the effective-time expression index only if representative history fixtures show an execution-plan or latency improvement. Do not create a locale-specific JSON expression index for the current 40-row list.

## Risks / Trade-offs

- [Shared DataGrid change affects every admin list] → Add component-level behavior coverage and browser-check at least one non-product DataGrid.
- [Batch history hydration could use excessive memory with large history sets] → Prefer a ranked subquery and limit input to current-page product IDs; add high-history test fixtures.
- [A queued image translation changes the submit response from completed results to accepted work] → Update the existing UI to poll status and preserve clear completed/failed feedback; keep response compatibility fields where practical.
- [Long-lived or multiple workers can expose request-state leakage] → Use existing scoped services, audit mutable singletons touched by the changed paths, and test different users/locales across consecutive requests.
- [OPcache can serve stale development code] → Keep timestamp validation enabled in the development profile and document restart/cache reset behavior.
- [Current machine cannot execute the container profile] → Validate repository config statically and repeat the real concurrency benchmark only when prerequisites exist; do not mark runtime performance targets complete from the single-process fallback.
- [Applying migrations changes local database state] → Use the tracked reversible migration, inspect `--pretend`, back up affected schema/data as appropriate, and use the migration's `down` only if no listing-history data must be retained.

## Migration Plan

1. Capture current migration status and product-list failure evidence.
2. Run the targeted migration in the local UnoPIM database and verify table/index definitions.
3. Implement DataGrid recovery and post-pagination history hydration; run focused PHP and frontend tests.
4. Implement polling, configuration, timeout, and queued-operation changes; run their focused tests.
5. Enable development OPcache and static-resource directives in the existing local FPM profile; update commands and prerequisites in `docs`.
6. Start the appropriate local service only for validation, run browser and concurrency checks, then stop services started by this change.
7. If any schema or response compatibility regression occurs, roll back code first. Roll back the migration only when retained history is not required; otherwise preserve the table and correct the application path forward.

## Open Questions

- The machine currently lacks Docker, so the final measured multi-worker numbers must be collected after that documented prerequisite is available; static validation and single-process OPcache comparison remain available immediately.
