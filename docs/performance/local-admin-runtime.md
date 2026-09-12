# 本地管理端性能运行手册

## 已定位的瓶颈

产品列表变慢由几个独立因素叠加造成：`product_listing_histories` 未迁移时 AJAX 直接返回 500；共享 DataGrid 原来只在成功回调中结束加载，因而持续显示骨架屏；产品分页 SQL 为每个候选商品执行相关的最新上架记录查询；列表立即下载原始商品图；Windows 的 `php artisan serve` 是单进程服务，且当前 PHP CLI 未加载 OPcache，并发请求只能排队。

优化后，产品基础分页不再连接上架历史表，只对当前页商品执行一次批量最新记录查询。列表图片使用浏览器原生懒加载。DataGrid 请求有 15 秒超时、取消、请求代次保护、可见错误和重试，并保留上一次成功结果。通知请求改为非重叠自调度，隐藏页面暂停，失败间隔按 15/30/60/120 秒退避。图片翻译提交立即返回 HTTP 202，执行状态持久化并由 `image-translations` 专用队列处理。

## 数据库与查询证据

迁移修复后的首次真实产品页包含 40 个顶层商品、每页 10 条。优化前 CLI 请求为 HTTP 200、24,163 bytes、19 条 SQL、SQL 合计 63.46 ms、应用处理 147.08 ms；真实浏览器列表 AJAX 为 1,074 ms。完整 PostgreSQL `EXPLAIN (ANALYZE, BUFFERS)` 中，默认排序为 0.237 ms，名称排序为 4.065 ms；当时历史表为空，但相关子查询仍对 40 个候选商品执行 40 次。

优化后名称排序的 CLI 请求为 HTTP 200、24,163 bytes、20 条 SQL、SQL 合计 31.25 ms、应用处理 131.74 ms。基础列表 SQL 为 5.64 ms，当前页历史批量查询为 1.57 ms。查询数会随所选动态列略有变化，历史查询固定为一次，不随页面记录数线性增长。

索引验证在事务中为 40 个商品临时生成 4,000 条历史记录。当前批量查询在既有索引下执行 1.009 ms；加入候选表达式索引后为 0.979 ms，执行计划仍选择顺序扫描和排序，差异 0.030 ms。事务已回滚，因此没有新增索引迁移。

浏览器优化后导航 TTFB 为 818 ms、load 为 1,552 ms；列表 AJAX 为 1,154 ms。可视区外的 10 张原始商品图均带 `loading=lazy`、`decoding=async`，检测时尚未下载。请求时间仍受当前单进程 `artisan serve` 排队影响，必须使用下面的 Nginx/PHP-FPM 配置才能验证并发目标。

2026-09-12 在已登录的 OpenCLI 浏览器中预热 3 次后，对当前 `127.0.0.1:8010` 服务各采样 10 个成功响应：单并发 P50 为 426.4 ms、P95/P99 为 496.6 ms、成功吞吐为 2.293 req/s；8 并发 P50 为 2,143.3 ms、P95/P99 为 3,440.7 ms、成功吞吐为 2.335 req/s。全部响应均为 HTTP 200，但未达到单请求 P95 300 ms 和 8 并发 P95 1 秒目标。进程检查确认该端口运行的是单进程 `php artisan serve`，并发吞吐基本不随并发数增加，排队是当前剩余的主要瓶颈。

本机没有 Docker 和 Nginx 可执行文件，因此未运行 `docker compose`、`nginx -t` 或真实 PHP-FPM 多 worker 压测。仓库配置已经完成静态检查：Compose YAML 可解析，Shell 启动脚本通过 `bash -n`，PHP 能加载 `php-dev.ini`，Nginx 片段通过 Crossplane 严格指令及参数检查。必须在具备 Docker 的机器上按本文启动开发栈后复测，才能判断多 worker 目标是否达到。

## 启动与检查

先检查 Docker 和 Compose：

```powershell
Get-Command docker
docker compose version
```

开发栈使用 Nginx、PHP-FPM、PostgreSQL、Redis、Elasticsearch、普通队列和图片翻译专用队列：

```powershell
docker compose -f compose.dev.yaml up -d --build
docker compose -f compose.dev.yaml ps
```

容器启动会执行迁移，并在开放 FPM 前运行下面的模式检查。设置 `UNOPIM_SKIP_MIGRATIONS=true` 时也会检查；只要存在待迁移文件，容器就会明确报错并退出。

```powershell
php artisan migrate --force
php artisan unopim:schema:check
```

变更配置后重建 Laravel 缓存：

```powershell
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm run build
```

开发 FPM 已启用 OPcache，并保留时间戳校验。验证命令：

```powershell
docker compose -f compose.dev.yaml exec unopim-fpm php -r "var_export(opcache_get_status(false)['opcache_enabled'] ?? false);"
```

性能采样时关闭 IDE 中可选的 Laravel Boost browser-logs 收集，再重新载入页面，避免调试日志请求污染网络瀑布。该设置仅影响本地调试工具；应用的生产和非调试行为不变。

## 队列和连接预算

图片翻译只能由专用 worker 消费。容器已使用有界参数 `--queue=image-translations --timeout=300 --tries=2 --max-jobs=100 --max-time=3600`。不使用 Docker 时运行：

```powershell
php artisan queue:work --queue=image-translations --timeout=300 --tries=2 --max-jobs=100 --max-time=3600
```

普通 worker 的队列列表不包含 `image-translations`，专用 worker 也不会消费 `system` 队列中的既有任务。

PHP-FPM 当前 `pm.max_children=20`，启动 4 个进程，满足至少 4 个并发 worker。按一个 PHP worker 最多占用一个数据库连接估算，再计入普通队列、图片翻译、调度器和维护命令，本地应用连接预算为 30；PostgreSQL 默认 100 个连接仍有充足余量。先根据实际 PHP 进程内存和数据库活动连接调低或调高 FPM worker，不要在没有连接耗尽证据时提高 PostgreSQL `max_connections`。

## 有界性能验证

在浏览器登录后，将 Cookie 请求头值写入工作区外的临时文件，再运行：

```powershell
python tools/benchmark_product_grid.py --url "http://127.0.0.1:8000/admin/catalog/products?channel=default&locale=zh_CN&pagination%5Bpage%5D=1&pagination%5Bper_page%5D=10" --cookie-file "$env:TEMP\unopim-cookie.txt" --concurrency 1 4 8 16 --count 16 --output "$env:TEMP\unopim-product-benchmark.json"
```

脚本为每个样本创建独立 HTTP opener，报告状态分布、成功吞吐、P50、P95、P99；任一采样不是 2xx 时退出码为 1，失败响应不会计入成功吞吐。预热后的目标为单请求 P95 不高于 300 ms、8 并发 P95 不高于 1 秒。

## 本次验证结果

以下检查已在本地完成：

```powershell
php artisan test packages/Webkul/Admin/tests/Feature/Catalog/ProductListingHistoryTest.php packages/Webkul/Admin/tests/Feature/Catalog/ProductImageTranslationQueueTest.php packages/Webkul/Admin/tests/Feature/Catalog/ProductWorkbenchActionsTest.php packages/Webkul/Admin/tests/Feature/Catalog/ProductDataGridSortInjectionTest.php packages/Webkul/Admin/tests/Feature/Catalog/ProductDataGridInheritedCommonAttributeTest.php packages/Webkul/Admin/tests/Feature/Catalog/ProductDataGridFilterableAttributePerformanceTest.php
npm install --no-package-lock
npm run build
php artisan unopim:schema:check
php artisan route:list --path=admin/catalog/products
vendor\bin\pint --test app/Console/Commands/CheckSchemaReadiness.php packages/Webkul/Admin/src/Jobs/TranslateProductImages.php packages/Webkul/Admin/src/Services/ProductImageTranslationService.php packages/Webkul/AdminApi/src/Database/Migrations/2026_09_12_010000_create_product_image_translation_jobs_table.php packages/Webkul/Admin/tests/Feature/Catalog/ProductImageTranslationQueueTest.php packages/Webkul/Admin/tests/Feature/Catalog/ProductListingHistoryTest.php packages/Webkul/Admin/tests/Feature/Catalog/ProductWorkbenchActionsTest.php
git diff --check
bash -n dockerfiles/lib/setup-app.sh dockerfiles/fpm-entrypoint.sh dockerfiles/q-entrypoint.sh
```

PHP 聚焦测试为 30 个测试、158 个断言，全部通过；Vite 生产构建和选定变更文件的 Pint 格式检查通过；模式检查、路由检查、变更空白检查和 Shell 语法检查均通过。OpenCLI 在真实产品列表及用户列表上完成分页、名称排序、筛选、保存视图、错误保留、重试、请求竞态、通知轮询和图片翻译状态轮询检查；浏览器租约已关闭。验证没有创建图片翻译任务，`system` 队列中的 75 个既有任务未被消费。

## 停止与回退

完成验证后停止本次开发栈和 worker：

```powershell
docker compose -f compose.dev.yaml down
```

回退运行时优化时还原 `dockerfiles/php-dev.ini`、`dockerfiles/nginx.conf` 和 `compose.dev.yaml` 后重建容器。回退图片翻译队列前先停止专用 worker；确认没有需要保留的任务和结果后，再针对对应迁移文件执行 `php artisan migrate:rollback --path=packages/Webkul/AdminApi/src/Database/Migrations/2026_09_12_010000_create_product_image_translation_jobs_table.php`。
