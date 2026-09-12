<?php

namespace Webkul\Admin\Http\Controllers\Collection;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Throwable;
use Webkul\Admin\Http\Controllers\Controller;

class Collection1688Controller extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! bouncer()->hasPermission('collection.1688')) {
                abort(403);
            }

            return $next($request);
        });
    }

    /**
     * Display the 1688 collection dashboard or fetch tasks JSON.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax() || request()->wantsJson()) {
            return $this->fetchJobs();
        }

        return view('admin::collection.1688.index');
    }

    /**
     * Submit a new 1688 product URL to start asynchronous collection.
     */
    public function store(): JsonResponse
    {
        if (! bouncer()->hasPermission('collection.1688.create')) {
            abort(403);
        }

        $validated = request()->validate([
            'url' => ['required', 'string', 'max:2000'],
        ]);

        $url = trim($validated['url']);
        if (! preg_match('/1688\.com\/offer\/(\d+)\.html/i', $url) && ! preg_match('/offerId=(\d+)/i', $url)) {
            return response()->json([
                'success' => false,
                'message' => '请输入有效的 1688 商品详情链接（需包含 offerId 或形如 detail.1688.com/offer/xxx.html）',
            ], 422);
        }

        try {
            $response = Http::timeout(10)->post($this->getPimUrl() . '/api/jobs/async-import', [
                'url'            => $url,
                'target_region'  => 'MY',
                'content_locale' => 'zh_CN',
            ]);

            if (! $response->successful()) {
                $error = $response->json('message') ?? $response->json('detail') ?? 'PIM 服务响应异常';
                return response()->json(['success' => false, 'message' => "启动采集失败: {$error}"], $response->status());
            }

            return response()->json([
                'success' => true,
                'message' => '采集任务已提交，系统正在后台启动抓取与解析...',
                'data'    => $response->json('data'),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => '无法连接到 PIM 采集服务 (127.0.0.1:8020)，请确认本地服务是否启动。',
            ], 502);
        }
    }

    /**
     * Re-trigger collection for an existing job.
     */
    public function retry(string $jobId): JsonResponse
    {
        if (! bouncer()->hasPermission('collection.1688.edit')) {
            abort(403);
        }

        try {
            $response = Http::timeout(10)->post($this->getPimUrl() . "/api/jobs/{$jobId}/retry");

            if (! $response->successful()) {
                $error = $response->json('message') ?? $response->json('detail') ?? 'PIM 服务响应异常';
                return response()->json(['success' => false, 'message' => "重新采集失败: {$error}"], $response->status());
            }

            return response()->json([
                'success' => true,
                'message' => '已重新发起采集任务，正在后台执行...',
                'data'    => $response->json('data'),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => '连接 PIM 采集服务失败: ' . $e->getMessage(),
            ], 502);
        }
    }

    /**
     * Update collection source URL for an existing job.
     */
    public function update(string $jobId): JsonResponse
    {
        if (! bouncer()->hasPermission('collection.1688.edit')) {
            abort(403);
        }

        $validated = request()->validate([
            'source_url' => ['required', 'string', 'max:2000'],
        ]);

        $url = trim($validated['source_url']);
        if (! preg_match('/1688\.com\/offer\/(\d+)\.html/i', $url) && ! preg_match('/offerId=(\d+)/i', $url)) {
            return response()->json([
                'success' => false,
                'message' => '请输入有效的 1688 商品详情链接',
            ], 422);
        }

        try {
            $response = Http::timeout(10)->patch($this->getPimUrl() . "/api/jobs/{$jobId}", [
                'source_url' => $url,
            ]);

            if (! $response->successful()) {
                $error = $response->json('message') ?? $response->json('detail') ?? '更新失败';
                return response()->json(['success' => false, 'message' => $error], $response->status());
            }

            return response()->json([
                'success' => true,
                'message' => '采集链接已成功修改。',
                'data'    => $response->json('data'),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => '连接 PIM 采集服务失败: ' . $e->getMessage(),
            ], 502);
        }
    }

    /**
     * Delete a collection job and completely clear its files.
     */
    public function destroy(string $jobId): JsonResponse
    {
        if (! bouncer()->hasPermission('collection.1688.delete')) {
            abort(403);
        }

        try {
            $response = Http::timeout(15)->delete($this->getPimUrl() . "/api/jobs/{$jobId}");

            if (! $response->successful()) {
                $error = $response->json('message') ?? $response->json('detail') ?? '删除失败';
                return response()->json(['success' => false, 'message' => $error], $response->status());
            }

            return response()->json([
                'success' => true,
                'message' => '采集任务及本地生成的 HTML/媒体文件已彻底删除清空。',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => '连接 PIM 采集服务失败: ' . $e->getMessage(),
            ], 502);
        }
    }

    /**
     * Helper to fetch jobs from PIM API.
     */
    protected function fetchJobs(): JsonResponse
    {
        $status = request()->query('status');
        $search = request()->query('search');
        $limit = max(1, min(100, (int) request()->query('limit', 20)));
        $page = max(1, (int) request()->query('page', 1));
        $offset = ($page - 1) * $limit;

        $params = [
            'limit'  => $limit,
            'offset' => $offset,
        ];
        if (! empty($status)) {
            $params['status'] = $status;
        }
        if (! empty($search)) {
            $params['search'] = $search;
        }

        try {
            $response = Http::timeout(5)->get($this->getPimUrl() . '/api/jobs', $params);

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'PIM 接口响应错误 HTTP ' . $response->status(),
                    'records' => [],
                    'total'   => 0,
                ], 502);
            }

            $json = $response->json();
            $rawRecords = $json['data'] ?? [];
            $skus = collect($rawRecords)->map(function ($record) {
                return $record['offer_id'] ?? ($record['product']['sku'] ?? null);
            })->filter()->unique()->values()->all();

            $products = ! empty($skus)
                ? \Webkul\Product\Models\Product::whereIn('sku', $skus)->get()->keyBy('sku')
                : collect();

            $records = array_map(function ($record) use ($products) {
                $sku = $record['offer_id'] ?? ($record['product']['sku'] ?? null);
                if ($sku && isset($products[$sku])) {
                    $p = $products[$sku];
                    $img = $p->values['common']['image'] ?? null;
                    if (! empty($img)) {
                        $imgFile = is_array($img) ? ($img[0] ?? '') : $img;
                        if (! empty($imgFile)) {
                            $record['thumbnail_url'] = \Illuminate\Support\Facades\Storage::url($imgFile);
                        }
                    }
                    $name = $p->values['common']['name'] ?? null;
                    if (! empty($name)) {
                        $record['title'] = is_array($name) ? ($name['zh_CN'] ?? reset($name)) : $name;
                    }
                }
                return $record;
            }, $rawRecords);

            return response()->json([
                'success' => true,
                'records' => $records,
                'total'   => $json['total'] ?? 0,
                'page'    => $page,
                'limit'   => $limit,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => '无法连接到 PIM 服务 (127.0.0.1:8020)，请确保本地服务已启动。',
                'records' => [],
                'total'   => 0,
            ], 502);
        }
    }

    /**
     * Resolve PIM service base URL.
     */
    protected function getPimUrl(): string
    {
        return rtrim(
            config('services.product_info_management.base_url'),
            '/'
        );
    }
}
