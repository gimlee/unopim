<?php

namespace Webkul\Category\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Webkul\Category\Services\TaxonomySynchronizer;
use Webkul\Category\Models\Category;
use Webkul\Category\Services\ProductCategoryAiClassifier;
use Webkul\Category\Services\ProductCategoryClassificationManager;
use Webkul\MagicAI\Enums\AiProvider;
use Webkul\MagicAI\Models\MagicAIPlatform;
use Webkul\Product\Models\Product;

class TaxonomyController extends Controller
{
    public function sync(Request $request, TaxonomySynchronizer $synchronizer): JsonResponse
    {
        set_time_limit(300);
        $payload = $request->validate([
            'categories' => ['sometimes', 'array'],
            'platform_taxonomies' => ['sometimes', 'array'],
            'source_mappings' => ['sometimes', 'array'],
            'assignments' => ['sometimes', 'array'],
            'fix_tree' => ['sometimes', 'boolean'],
            'migrate_source_category' => ['sometimes', 'nullable', 'string'],
            'fallback_category' => ['sometimes', 'nullable', 'string'],
            'remove_categories' => ['sometimes', 'array'],
            'remove_categories.*' => ['string', 'max:255'],
            'sync_state' => ['sometimes', 'array'],
            'sync_state.key' => ['required_with:sync_state', 'string', 'max:255'],
            'sync_state.version' => ['required_with:sync_state', 'string', 'max:255'],
            'sync_state.metadata' => ['sometimes', 'array'],
        ]);

        return response()->json(['success' => true, 'data' => $synchronizer->sync($payload)]);
    }

    public function status(Request $request): JsonResponse
    {
        $data = $request->validate(['key' => ['required', 'string', 'max:255']]);
        $state = DB::table('taxonomy_sync_states')->where('key', $data['key'])->first();

        return $state
            ? response()->json([
                'key' => $state->key,
                'version' => $state->version,
                'metadata' => json_decode($state->metadata ?: '[]', true),
                'synced_at' => $state->synced_at,
            ])
            : response()->json(['message' => 'Taxonomy sync state not found.'], 404);
    }

    public function classifier(): JsonResponse
    {
        $categories = Category::query()
            ->where('taxonomy_type', 'standard')
            ->where('is_assignable', true)
            ->where('status', 'active')
            ->with(['aliases', 'classificationRules' => fn ($query) => $query->where('status', true)->orderBy('position')])
            ->orderBy('source_path')
            ->get()
            ->map(fn (Category $category): array => [
                'code' => $category->code,
                'path' => $category->source_path ?: $category->name,
                'source_path' => $category->source_path,
                'labels' => $category->additional_data['locale_specific'] ?? [],
                'aliases' => $category->aliases->pluck('alias')->values()->all(),
                'classification_rules' => $category->classificationRules->map(fn ($rule): array => [
                    'rule_type' => $rule->rule_type,
                    'field' => $rule->field,
                    'operator' => $rule->operator,
                    'value' => $rule->value,
                    'weight' => (float) $rule->weight,
                ])->values()->all(),
                'is_assignable' => true,
            ])->values();

        return response()->json(['data' => $categories]);
    }

    public function resolve(Request $request, TaxonomySynchronizer $synchronizer): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string'],
            'platform' => ['required', 'string'],
            'region' => ['sometimes', 'nullable', 'string', 'max:16'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
        $result = $synchronizer->resolve(
            $data['category'],
            $data['platform'],
            $data['region'] ?? null,
            $data['sku'] ?? null
        );

        return $result
            ? response()->json($result)
            : response()->json(['message' => 'No confirmed product assignment and platform category mapping found.'], 404);
    }

    public function classifyProductWithAi(
        Request $request,
        ProductCategoryAiClassifier $classifier,
        ProductCategoryClassificationManager $manager,
        string $sku
    ): JsonResponse {
        // Magic AI uses an HTTP timeout longer than PHP's CLI/server default.
        // Keep this endpoint alive long enough to return the provider's real
        // response or timeout instead of an unrelated 30-second fatal error.
        set_time_limit(180);

        $data = $request->validate([
            'platform' => ['sometimes', 'in:tiktok'],
            'region'   => ['sometimes', 'string', 'size:2'],
        ]);
        $product = Product::query()->where('sku', $sku)->firstOrFail();
        $product = $product->parent ?: $product;
        $platform = MagicAIPlatform::query()
            ->where('status', true)
            ->where('provider', '!=', AiProvider::ZhipuCodePlan->value)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->first(fn (MagicAIPlatform $candidate): bool => count($candidate->model_list) > 0);

        if (! $platform) {
            return response()->json([
                'message' => '没有可用于商品分类的 Magic AI 平台；请先启用通用 AI 平台并配置模型。',
            ], 422);
        }

        $model = collect($platform->model_list)
            ->first(fn (string $name): bool => str_contains(strtolower($name), 'flash'))
            ?: $platform->model_list[0];
        $manager->remember($product, 'ai', $classifier->classify($product, $platform, $model));
        $applied = $manager->apply($product, 'ai', null);

        return response()->json([
            'success' => true,
            'data'    => $manager->present($applied),
            'meta'    => [
                'platform' => $data['platform'] ?? 'tiktok',
                'region'   => strtoupper($data['region'] ?? 'MY'),
                'provider' => $platform->label,
                'model'    => $model,
            ],
        ]);
    }
}
