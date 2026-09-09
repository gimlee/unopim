<?php

namespace Webkul\Admin\Http\Controllers\Catalog;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Webkul\Admin\Http\Controllers\Controller;
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

        return response()->json([
            'success'           => true,
            'product'           => [
                'id'  => $rootProduct->id,
                'sku' => $rootProduct->sku,
            ],
            'images'            => $images,
            'translations'      => $translations,
            'candidate_regions' => $this->candidateRegions,
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

        set_time_limit(300);

        $product = $this->productRepository->findOrFail($id);
        $rootProduct = $product->parent ?: $product;

        $targetLang = $request->input('target_lang', 'th');
        $sourceLang = $request->input('source_lang', 'zh');
        $region = strtoupper($request->input('region', 'TH'));
        $platform = $request->input('platform', 'aeAi');
        $selectedImages = $request->input('images');

        $pimUrl = $this->getPimUrl();

        $results = [];
        $errors = [];

        foreach ($selectedImages as $item) {
            $rawPath = $item['path'] ?? $item['original_url'] ?? '';
            if (! $rawPath) {
                continue;
            }

            try {
                $translatedUrl = null;
                $isRemote = str_starts_with($rawPath, 'http://') || str_starts_with($rawPath, 'https://');

                if (! $isRemote) {
                    $disk = Storage::disk('public');
                    $fullLocalPath = $disk->path($rawPath);

                    if (file_exists($fullLocalPath)) {
                        $uploadResponse = Http::timeout(120)
                            ->attach('file', file_get_contents($fullLocalPath), basename($fullLocalPath))
                            ->post("{$pimUrl}/api/image-translation/upload-and-translate", [
                                'source_lang'        => $sourceLang,
                                'target_lang'        => $targetLang,
                                'translate_platform' => $platform,
                            ]);

                        if ($uploadResponse->successful()) {
                            $resData = $uploadResponse->json();
                            $items = $resData['results'] ?? [];
                            if (! empty($items)) {
                                $translatedUrl = $items[0]['translated_url'] ?? null;
                            } else {
                                $translatedUrl = $resData['translated_url'] ?? null;
                            }
                        } else {
                            $errMsg = $uploadResponse->json('detail') ?: ('PIM 翻译上传失败 HTTP ' . $uploadResponse->status());
                            $errors[] = "图片 {$rawPath} 翻译失败: {$errMsg}";
                        }
                    } else {
                        $errors[] = "本地图片文件不存在: {$rawPath}";
                    }
                } else {
                    $transResponse = Http::timeout(120)
                        ->post("{$pimUrl}/api/image-translation/translate", [
                            'image_urls'         => [$rawPath],
                            'source_lang'        => $sourceLang,
                            'target_lang'        => $targetLang,
                            'translate_platform' => $platform,
                        ]);

                    if ($transResponse->successful()) {
                        $resData = $transResponse->json();
                        $items = $resData['results'] ?? [];
                        if (! empty($items)) {
                            $translatedUrl = $items[0]['translated_url'] ?? null;
                        }
                    } else {
                        $errMsg = $transResponse->json('detail') ?: ('PIM 翻译接口失败 HTTP ' . $transResponse->status());
                        $errors[] = "图片 {$rawPath} 翻译失败: {$errMsg}";
                    }
                }

                if ($translatedUrl) {
                    $results[] = [
                        'original_url'   => $rawPath,
                        'original_view'  => $isRemote ? $rawPath : Storage::url($rawPath),
                        'translated_url' => $translatedUrl,
                        'image_type'     => $item['type'] ?? 'gallery',
                        'variant_sku'    => $item['variant_sku'] ?? null,
                        'region'         => $region,
                        'locale'         => $targetLang,
                        'status'         => 'success',
                    ];
                }
            } catch (Throwable $e) {
                report($e);
                $errors[] = "处理图片 {$rawPath} 异常: " . $e->getMessage();
            }
        }

        if (empty($results) && ! empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => implode('; ', array_slice($errors, 0, 3)),
                'errors'  => $errors,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => '图片翻译成功，已生成 ' . count($results) . ' 张译图',
            'results' => $results,
            'errors'  => $errors,
        ]);
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
                $imgResponse = Http::timeout(30)->get($translatedUrl);
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

    /**
     * Resolve PIM service base URL.
     */
    protected function getPimUrl(): string
    {
        return rtrim(
            config('services.product_info_management.base_url')
            ?: config('services.pim.url')
            ?: env('PRODUCT_INFO_MANAGEMENT_BASE_URL')
            ?: env('PIM_API_URL', 'http://127.0.0.1:8020'),
            '/'
        );
    }
}
