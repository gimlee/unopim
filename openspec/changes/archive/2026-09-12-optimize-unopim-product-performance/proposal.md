## Why

The UnoPIM product page currently renders its shell but the product-grid request fails because an existing database migration has not been applied, leaving the interface on an endless loading skeleton. The current Windows `artisan serve` process also serializes requests and recompiles a large Laravel application without OPcache, so ordinary administration requests queue sharply under concurrency.

## What Changes

- Restore the product grid against installations whose schema is upgraded through the repository migrations, and verify the latest-listing projection does not duplicate products or corrupt pagination totals.
- Make the shared DataGrid request lifecycle bounded and recoverable: failures and timeouts leave loading state, show an actionable message, support retry, and prevent stale responses from overwriting newer state.
- Reduce avoidable product-page work by loading listing history only for the current page, serving product thumbnails efficiently, and measuring the complete successful query before adding any growth-oriented index.
- Reduce global background traffic by pausing notification polling for hidden tabs, preventing overlapping polls, and applying bounded failure backoff.
- Consolidate PIM service URL reads into Laravel configuration so optimized configuration caching preserves the configured endpoint.
- Provide a documented local performance runtime using the repository's existing Nginx/PHP-FPM configuration with OPcache and multiple workers, plus explicit start, stop, migration, queue-worker, cache, and verification commands. This remains local and is never deployed to Sites.
- Keep long-running image translation and listing automation out of ordinary web-worker occupancy by using queued orchestration or a short downstream acceptance timeout where the downstream endpoint already schedules asynchronous work.
- Add focused regression and performance verification for successful product-list requests, failure recovery, polling behavior, schema readiness, and concurrent local requests.

## Capabilities

### New Capabilities

- `admin-runtime-performance`: Defines recoverable admin data loading, bounded background polling, local multi-worker runtime, queue isolation, and measurable concurrency behavior.

### Modified Capabilities

- `catalog-product`: Extends the product catalog contract so the grouped product list remains correct and usable when listing history is absent, present in volume, or temporarily unavailable.

## Impact

- Affected repository: `D:\MyGithub\unopim`.
- Primary code areas: Admin DataGrid Vue template, product DataGrid/query path, notification header component, product/image-translation controllers, service configuration, existing Admin API migrations, local runtime files, and documentation.
- Database: apply the already tracked `product_listing_histories` migration; introduce an additional index only if a successful full-query `EXPLAIN (ANALYZE, BUFFERS)` demonstrates benefit. Migration execution is a local operational step and must be recorded in Laravel's migration table.
- Runtime: the current Windows PHP installation has the OPcache DLL but does not load it; Docker is not currently installed. Runtime work must therefore be repository-contained and documented, with environment prerequisites made explicit rather than silently changing global PHP or Windows features.
- Compatibility: existing product routes and response shapes remain compatible. Shared DataGrid behavior improves for all admin lists, so regression coverage must include another DataGrid page.
- Operations: no remote Sites deployment, no automatic commit or push, and no permanently running service after validation.
