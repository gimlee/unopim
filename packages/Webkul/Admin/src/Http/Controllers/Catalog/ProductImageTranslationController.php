<?php

namespace Webkul\Admin\Http\Controllers\Catalog;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Jobs\TranslateProductImages;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductImageTranslation;
use Webkul\Product\Repositories\ProductRepository;

class ProductImageTranslationController extends Controller
{
    /**
     * Candidate regions and supported target languages.
     */
    protected array $candidateRegions = [
        ['code' => 'TH', 'name' => '泰国', 'lang' => 'th', 'lang_name' => '泰文 (Thai)'],
        ['code' => 'MY', 'name' => '马来西亚', 'lang' => 'ms', 'lang_name' => '马来文 (Malay)'],
        ['code' => 'SG', 'name' => '新加坡/国际', 'lang' => 'en', 'lang_name' => '英文 (English)'],
        ['code' => 'VN', 'name' => '越南', 'lang' => 'vi', 'lang_name' => '越南文 (Vietnamese)'],
        ['code' => 'PH', 'name' => '菲律宾', 'lang' => 'fil', 'lang_name' => '菲律宾文 (Filipino)'],
        ['code' => 'ID', 'name' => '印尼', 'lang' => 'id', 'lang_name' => '印尼文 (Indonesian)'],
    ];

    public function __construct(protected ProductRepository $productRepository)
    {
        $this->middleware(function ($request, $next) {
            abort_unless(bouncer()->hasPermission('catalog.products.edit'), 403);

            return $next($request);
        });
    }

    /**
     * Get categorized images and existing translations for a product.
     */
    public function index(int $id): JsonResponse
    {
        $product = $this->productRepository->findOrFail($id);
        $rootProduct = $product->parent ?: $product;

        $images = $this->extractClassifiedImages($rootProduct);

        $translations = ProductImageTranslation::query()
            ->where('product_id', $rootProduct->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function ($item) {
                return [
                    'id'             => $item->id,
                    'sku'            => $item->sku,
                    'region'         => $item->region,
                    'locale'         => $item->locale,
                    'image_type'     => $item->image_type,
                    'original_url'   => $item->original_url,
                    'translated_url' => $item->translated_url,
                    'local_path'     => $item->local_path,
                    'local_url'      => $item->local_path ? Storage::url($item->local_path) : null,
                    'variant_sku'    => $item->variant_sku,
                    'sort_order'     => $item->sort_order,
                    'created_at'     => $item->created_at?->toIso8601String(),
                ];
            });

        $activeJob = DB::table('product_image_translation_jobs')
            ->where('product_id', $rootProduct->id)
            ->whereIn('status', ['queued', 'running'])
            ->latest('created_at')
            ->first();

        return response()->json([
            'success'           => true,
            'product'           => [
                'id'  => $rootProduct->id,
                'sku' => $rootProduct->sku,
            ],
            'images'            => $images,
            'translations'      => $translations,
            'candidate_regions' => $this->candidateRegions,
            'active_job'        => $activeJob ? $this->formatTranslationJob($activeJob) : null,
        ]);
    }

    /**
     * Call PIM image translation service for selected images.
     */
    public function translate(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'images'        => ['required', 'array', 'min:1'],
            'target_lang'   => ['required', 'string', 'max:16'],
            'region'        => ['required', 'string', 'max:16'],
            'source_lang'   => ['nullable', 'string', 'max:16'],
            'platform'      => ['nullable', 'string', 'max:32'],
        ]);

        $product = $this->productRepository->findOrFail($id);
        $rootProduct = $product->parent ?: $product;

        $payload = [
            'images'      => $request->input('images'),
            'target_lang' => $request->input('target_lang', 'th'),
            'source_lang' => $request->input('source_lang', 'zh'),
            'region'      => strtoupper($request->input('region', 'TH')),
            'platform'    => $request->input('platform', 'aeAi'),
        ];

        $jobState = DB::transaction(function () use ($rootProduct, $payload): array {
            DB::table('products')->where('id', $rootProduct->id)->lockForUpdate()->value('id');

            $existingJob = DB::table('product_image_translation_jobs')
                ->where('product_id', $rootProduct->id)
                ->whereIn('status', ['queued', 'running'])
                ->latest('created_at')
                ->first();

            if ($existingJob) {
                return ['id' => $existingJob->id, 'status' => $existingJob->status, 'created' => false];
            }

            $jobId = (string) Str::uuid();

            DB::table('product_image_translation_jobs')->insert([
                'id'         => $jobId,
                'product_id' => $rootProduct->id,
                'status'     => 'queued',
                'payload'    => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['id' => $jobId, 'status' => 'queued', 'created' => true];
        });

        if (! $jobState['created']) {
            return response()->json([
                'success' => true,
                'status'  => $jobState['status'],
                'job_id'  => $jobState['id'],
                'message' => '该商品已有图片翻译任务正在执行',
            ], 202);
        }

        TranslateProductImages::dispatch($jobState['id']);

        return response()->json([
            'success' => true,
            'status'  => 'queued',
            'job_id'  => $jobState['id'],
            'message' => '图片翻译任务已进入队列',
        ], 202);
    }

    /**
     * Return persistent image-translation job status.
     */
    public function status(int $id, string $jobId): JsonResponse
    {
        $product = $this->productRepository->findOrFail($id);
        $rootProduct = $product->parent ?: $product;
        $job = DB::table('product_image_translation_jobs')
            ->where('id', $jobId)
            ->where('product_id', $rootProduct->id)
            ->first();

        abort_unless($job, 404);

        return response()->json([
            'success' => true,
            'data'    => $this->formatTranslationJob($job),
        ]);
    }

    protected function formatTranslationJob(object $job): array
    {
        return [
            'id'         => $job->id,
            'status'     => $job->status,
            'results'    => $job->results ? json_decode($job->results, true) : [],
            'errors'     => $job->errors ? json_decode($job->errors, true) : [],
            'error'      => $job->error,
            'created_at' => $job->created_at,
        ];
    }

    /**
     * Persist translated images to local storage and DB.
     */
    public function save(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'translations' => ['required', 'array', 'min:1'],
        ]);

        set_time_limit(300);

        $product = $this->productRepository->findOrFail($id);
        $rootProduct = $product->parent ?: $product;

        $items = $request->input('translations');
        $saved = [];

        foreach ($items as $index => $item) {
            $originalUrl = $item['original_url'] ?? '';
            $translatedUrl = $item['translated_url'] ?? '';
            $region = strtoupper($item['region'] ?? 'TH');
            $locale = $item['locale'] ?? 'th';
            $imageType = $item['image_type'] ?? 'gallery';
            $variantSku = $item['variant_sku'] ?? null;

            if (! $originalUrl || ! $translatedUrl) {
                continue;
            }

            // Download translated OSS image to local storage
            $localPath = null;
            try {
                $imgResponse = Http::connectTimeout(config('services.product_info_management.connect_timeout'))
                    ->timeout(config('services.product_info_management.download_timeout'))
                    ->get($translatedUrl);
                if ($imgResponse->successful()) {
                    $ext = 'jpg';
                    $urlPath = parse_url($translatedUrl, PHP_URL_PATH);
                    if ($urlPath && preg_match('/\.(jpg|jpeg|png|webp)/i', $urlPath, $m)) {
                        $ext = strtolower($m[1]);
                    }
                    $hash = substr(md5($originalUrl . '_' . $locale), 0, 16);
                    $subPath = $variantSku ? "variant_{$variantSku}" : "{$imageType}_{$index}";
                    $storagePath = "product/{$rootProduct->id}/translated/{$region}/{$subPath}_{$hash}.{$ext}";

                    Storage::disk('public')->put($storagePath, $imgResponse->body());
                    $localPath = $storagePath;
                }
            } catch (Throwable $e) {
                report($e);
            }

            $record = ProductImageTranslation::query()->updateOrCreate(
                [
                    'product_id'   => $rootProduct->id,
                    'region'       => $region,
                    'image_type'   => $imageType,
                    'original_url' => $originalUrl,
                    'variant_sku'  => $variantSku,
                ],
                [
                    'sku'            => $rootProduct->sku,
                    'locale'         => $locale,
                    'translated_url' => $translatedUrl,
                    'local_path'     => $localPath,
                    'sort_order'     => $index,
                    'metadata'       => [
                        'saved_at'   => now()->toIso8601String(),
                        'has_local'  => (bool) $localPath,
                    ],
                ]
            );

            $saved[] = [
                'id'             => $record->id,
                'region'         => $record->region,
                'locale'         => $record->locale,
                'image_type'     => $record->image_type,
                'original_url'   => $record->original_url,
                'translated_url' => $record->translated_url,
                'local_path'     => $record->local_path,
                'local_url'      => $record->local_path ? Storage::url($record->local_path) : null,
                'variant_sku'    => $record->variant_sku,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => '成功保存 ' . count($saved) . ' 张翻译图片',
            'data'    => $saved,
        ]);
    }

    /**
     * Delete a translation record and its local file.
     */
    public function destroy(int $id, int $translationId): JsonResponse
    {
        $product = $this->productRepository->findOrFail($id);
        $rootProduct = $product->parent ?: $product;

        $translation = ProductImageTranslation::query()
            ->where('product_id', $rootProduct->id)
            ->where('id', $translationId)
            ->first();

        if (! $translation) {
            return response()->json(['success' => false, 'message' => '翻译图片记录不存在'], 404);
        }

        if ($translation->local_path && Storage::disk('public')->exists($translation->local_path)) {
            Storage::disk('public')->delete($translation->local_path);
        }

        $translation->delete();

        return response()->json(['success' => true, 'message' => '翻译图片已删除']);
    }

    /**
     * Extract and classify all images of a product and its variants.
     */
    protected function extractClassifiedImages(Product $rootProduct): array
    {
        $images = [];
        $values = $rootProduct->values ?? [];
        $common = $values['common'] ?? [];

        // 1. Main image
        if (! empty($common['image'])) {
            $path = is_string($common['image']) ? $common['image'] : (string) reset($common['image']);
            $images[] = [
                'id'         => 'main_0',
                'type'       => 'main',
                'type_label' => '商品主图',
                'path'       => $path,
                'url'        => Storage::url($path),
            ];
        }

        // 2. Gallery images (轮播图)
        if (! empty($common['gallery'])) {
            $gallery = is_array($common['gallery']) ? $common['gallery'] : explode(',', (string) $common['gallery']);
            foreach ($gallery as $idx => $path) {
                $path = trim($path);
                if ($path) {
                    $images[] = [
                        'id'         => 'gallery_' . $idx,
                        'type'       => 'gallery',
                        'type_label' => '商品图库 #' . ($idx + 1),
                        'path'       => $path,
                        'url'        => Storage::url($path),
                    ];
                }
            }
        }

        // 3. Detail gallery images (详情图)
        if (! empty($common['source_detail_gallery'])) {
            $details = is_array($common['source_detail_gallery'])
                ? $common['source_detail_gallery']
                : explode(',', (string) $common['source_detail_gallery']);
            foreach ($details as $idx => $path) {
                $path = trim($path);
                if ($path) {
                    $images[] = [
                        'id'         => 'detail_' . $idx,
                        'type'       => 'detail_gallery',
                        'type_label' => '详情描述图 #' . ($idx + 1),
                        'path'       => $path,
                        'url'        => Storage::url($path),
                    ];
                }
            }
        }

        // 4. Variant images (SKU变种图)
        if ($rootProduct->variants && $rootProduct->variants->count() > 0) {
            foreach ($rootProduct->variants as $variant) {
                $vValues = $variant->values ?? [];
                $vCommon = $vValues['common'] ?? [];
                $vPath = $vCommon['image'] ?? null;
                if ($vPath) {
                    $vPath = is_string($vPath) ? $vPath : (string) reset($vPath);
                    $images[] = [
                        'id'          => 'variant_' . $variant->id,
                        'type'        => 'variant',
                        'type_label'  => 'SKU变种图',
                        'variant_sku' => $variant->sku,
                        'path'        => $vPath,
                        'url'         => Storage::url($vPath),
                    ];
                }
            }
        }

        return $images;
    }

}
