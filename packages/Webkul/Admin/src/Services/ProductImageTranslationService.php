<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductImageTranslationService
{
    public function translate(array $payload): array
    {
        $pimUrl = rtrim(config('services.product_info_management.base_url'), '/');
        $timeout = config('services.product_info_management.worker_timeout');
        $connectTimeout = config('services.product_info_management.connect_timeout');
        $results = [];
        $errors = [];

        foreach ($payload['images'] as $item) {
            $rawPath = $item['path'] ?? $item['original_url'] ?? '';

            if (! $rawPath) {
                continue;
            }

            try {
                $translatedUrl = null;
                $isRemote = str_starts_with($rawPath, 'http://') || str_starts_with($rawPath, 'https://');
                $request = Http::connectTimeout($connectTimeout)->timeout($timeout);

                if (! $isRemote) {
                    $fullLocalPath = Storage::disk('public')->path($rawPath);

                    if (! file_exists($fullLocalPath)) {
                        $errors[] = "本地图片文件不存在: {$rawPath}";

                        continue;
                    }

                    $response = $request
                        ->attach('file', file_get_contents($fullLocalPath), basename($fullLocalPath))
                        ->post("{$pimUrl}/api/image-translation/upload-and-translate", [
                            'source_lang'        => $payload['source_lang'],
                            'target_lang'        => $payload['target_lang'],
                            'translate_platform' => $payload['platform'],
                        ]);
                } else {
                    $response = $request->post("{$pimUrl}/api/image-translation/translate", [
                        'image_urls'         => [$rawPath],
                        'source_lang'        => $payload['source_lang'],
                        'target_lang'        => $payload['target_lang'],
                        'translate_platform' => $payload['platform'],
                    ]);
                }

                if (! $response->successful()) {
                    $detail = $response->json('detail') ?: "PIM 图片翻译失败 HTTP {$response->status()}";
                    $errors[] = "图片 {$rawPath} 翻译失败: {$detail}";

                    continue;
                }

                $translatedUrl = $response->json('results.0.translated_url') ?: $response->json('translated_url');

                if ($translatedUrl) {
                    $results[] = [
                        'original_url'   => $rawPath,
                        'original_view'  => $isRemote ? $rawPath : Storage::url($rawPath),
                        'translated_url' => $translatedUrl,
                        'image_type'     => $item['type'] ?? 'gallery',
                        'variant_sku'    => $item['variant_sku'] ?? null,
                        'region'         => $payload['region'],
                        'locale'         => $payload['target_lang'],
                        'status'         => 'success',
                    ];
                }
            } catch (Throwable $error) {
                report($error);
                $errors[] = "处理图片 {$rawPath} 异常: {$error->getMessage()}";
            }
        }

        return compact('results', 'errors');
    }
}
