<?php

namespace Webkul\Admin\Http\Controllers\Catalog;

use Illuminate\Http\JsonResponse;
use Throwable;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\AdminApi\Services\ProductContentPolicyService;
use Webkul\MagicAI\Models\MagicAISystemPrompt;
use Webkul\Product\Models\Product;

class ProductDescriptionAiController extends Controller
{
    public function __construct(protected ProductContentPolicyService $policy)
    {
        $this->middleware(function ($request, $next) {
            if (! bouncer()->hasPermission('catalog.products.edit') || ! bouncer()->hasPermission('ai-agent')) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function templates(): JsonResponse
    {
        return response()->json(['data' => $this->policy->descriptionTemplates()]);
    }

    public function updateTemplate(int $id): JsonResponse
    {
        $data = request()->validate([
            'title'   => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:20000'],
        ]);
        $template = MagicAISystemPrompt::query()
            ->where('purpose', 'product_description')
            ->findOrFail($id);
        $template->update([
            'title' => trim($data['title']),
            'tone'  => trim($data['content']),
        ]);

        return response()->json(['message' => '描述模板已保存。']);
    }

    public function optimize(int $id): JsonResponse
    {
        $data = request()->validate([
            'channel'                  => ['nullable', 'string', 'max:64'],
            'locale'                   => ['nullable', 'string', 'max:16'],
            'template_id'              => ['required', 'integer'],
            'source_name'              => ['nullable', 'string', 'max:20000'],
            'source_short_description' => ['nullable', 'string', 'max:50000'],
            'source_description'       => ['nullable', 'string', 'max:200000'],
        ]);
        $product = Product::query()->findOrFail($id);
        $product = $product->parent ?: $product;

        try {
            $result = $this->policy->optimizeShortDescription(
                $product,
                $data['locale'] ?? null,
                $data['channel'] ?? null,
                (int) $data['template_id'],
                array_filter([
                    'name'              => $data['source_name'] ?? null,
                    'short_description' => $data['source_short_description'] ?? null,
                    'description'       => $data['source_description'] ?? null,
                ], fn ($value): bool => $value !== null),
            );
        } catch (Throwable $error) {
            report($error);

            return response()->json(['message' => '商品描述 AI 优化失败：'.$error->getMessage()], 422);
        }

        return response()->json(['message' => 'Short Description 已完成 AI 优化并保存。', 'data' => $result]);
    }
}
