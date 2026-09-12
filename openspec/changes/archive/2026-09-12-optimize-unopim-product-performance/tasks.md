## 1. Schema Readiness and Baseline

- [x] 1.1 Inspect all pending UnoPIM migrations, run the tracked listing-history migration through Artisan after reviewing `--pretend`, and verify `product_listing_histories` plus its unique/latest indexes exist in the local PostgreSQL migrations ledger.
- [x] 1.2 Add a schema-readiness check to the documented local startup/upgrade flow and verify it fails clearly when required migrations are pending without silently starting a supposedly healthy service.
- [x] 1.3 Capture a successful post-migration product-grid baseline, including status, record count, query count, SQL time, response size, and full `EXPLAIN (ANALYZE, BUFFERS)` evidence for default and name sorting.

## 2. Product Grid Query Path

- [x] 2.1 Remove latest-listing joins from the base product pagination query, batch-load the latest listing record for current-page product IDs, and verify zero/one/many-history fixtures each return one correctly decorated product row.
- [x] 2.2 Add regression coverage for filtered pagination totals, stable ordering, no-history rendering, and no N+1 growth, then run the focused Admin ProductDataGrid and listing-history tests.
- [x] 2.3 Add `loading="lazy"` and `decoding="async"` to product and variation list images without changing their URLs, and verify the product-grid view test/build succeeds; defer thumbnail generation unless the restored browser trace proves original-image transfer is material.
- [x] 2.4 Compare the restored full-query plans with the candidate effective-history index and add a migration only if representative high-history fixtures show measurable benefit; record the measured before/after plan or mark this task complete with evidence that no new index is justified.

## 3. Recoverable Shared DataGrid

- [x] 3.1 Add request generation ownership, cancellation, and a bounded timeout to shared DataGrid loading, and verify an older delayed response cannot overwrite or end the loading state for a newer request.
- [x] 3.2 Add visible error and timeout states with retry while retaining applied filters and prior successful rows, and verify HTTP 500, malformed response, timeout, cancel, retry, and successful empty results produce distinct correct states.
- [x] 3.3 Run the shared DataGrid coverage and browser-check the product list plus one unrelated admin list to verify the common component remains compatible.

## 4. Background Request Reduction and Cache-Safe Configuration

- [x] 4.1 Replace fixed notification polling with non-overlapping self-scheduling polling, hidden-page suspension, visible-page refresh, and bounded failure backoff; verify timer, visibility, unmount, in-flight, success, and failure behavior in focused tests.
- [x] 4.2 Define canonical PIM base URL and connect/acceptance/worker timeout settings under Laravel configuration, remove controller-level `env()` fallbacks from the affected paths, and verify cached and uncached configuration resolve identical values.
- [x] 4.3 Disable or document disabling optional local browser-log collection during performance runs, and verify production/non-debug behavior remains unchanged.

## 5. Long-Running Work Isolation

- [x] 5.1 Change listing-launch HTTP calls to use the configured short connect/acceptance timeout while preserving returned attempt identity and status polling, and verify accepted, rejected, connection-failure, and acceptance-timeout tests.
- [x] 5.2 Extract existing image-translation execution into a dedicated queued job with persistent queued/running/completed/failed status, return HTTP 202 with a status identifier, and verify the Web request does not wait for the mocked translation service.
- [x] 5.3 Update the existing image-translation UI to poll the queued status and present completed or failed results, and verify submission, progress, completion, failure, refresh, and duplicate-submit behavior.
- [x] 5.4 Route image translation to its own named queue and add bounded worker, retry, timeout, and stop instructions; verify the worker command targets only that queue and does not consume the 75 existing `system` tasks during validation.

## 6. Local Multi-Worker Runtime

- [x] 6.1 Enable OPcache with timestamp validation in the existing development PHP-FPM profile and configure Nginx compression plus immutable caching for hashed build assets, then validate the PHP and Nginx configuration syntax.
- [x] 6.2 Confirm the existing PHP-FPM pool exposes at least four workers with an explicit database-connection budget, and document memory/worker tuning rather than increasing PostgreSQL `max_connections` without evidence.
- [x] 6.3 Add concise `docs` instructions for prerequisite detection, optimized local start/stop, migrations, Laravel caches, dedicated queue workers, OPcache verification, and rollback; verify every documented repository command exists and no instruction deploys to Sites.
- [x] 6.4 Add or adapt a bounded benchmark script that reports status, throughput, P50/P95/P99, supports independent sessions and 1/4/8/16 concurrency, and rejects non-2xx responses from successful throughput; verify it exits and cleans up any service it starts.

## 7. End-to-End Verification

- [x] 7.1 Run focused PHP tests, frontend tests/build, formatter/static checks applicable to changed files, and record exact commands and results.
- [x] 7.2 Start the local service in a visible validation setup, use OpenCLI to verify the real product page, pagination, name sort, filter, saved view, error recovery, retry, notification behavior, and image-translation queued flow, then close the browser lease and stop every service started for validation.
- [x] 7.3 Run warm single-request and 8-concurrency product-list measurements using only successful responses; compare with the stored baseline and report whether the 300 ms single-request and 1 s concurrent P95 targets were met.
- [x] 7.4 If the machine still lacks the documented multi-worker prerequisite, record static/runtime checks and the precise blocked measurement without claiming the concurrency target passed; verify no temporary port or worker remains listening.
