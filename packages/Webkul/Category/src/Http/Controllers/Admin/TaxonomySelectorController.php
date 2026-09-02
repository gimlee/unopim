<?php

namespace Webkul\Category\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Webkul\Category\Models\Category;
use Webkul\Category\Models\PlatformCategory;
use Webkul\Category\Models\PlatformTaxonomy;

class TaxonomySelectorController extends Controller
{
    public function options(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'      => ['required', Rule::in(['standard', 'platform'])],
            'parent_id' => ['nullable', 'integer'],
            'selected'  => ['nullable', 'string', 'max:255'],
            'platform'  => ['nullable', 'string', 'max:64'],
            'region'    => ['nullable', 'string', 'max:16'],
        ]);

        return $data['type'] === 'standard'
            ? $this->standardOptions($data)
            : $this->platformOptions($data);
    }

    protected function standardOptions(array $data): JsonResponse
    {
        $selected = ! empty($data['selected'])
            ? Category::query()
                ->where('code', $data['selected'])
                ->where('taxonomy_type', 'standard')
                ->first()
            : null;

        if ($selected) {
            $path = Category::query()
                ->whereAncestorOf($selected, true)
                ->whereIn('taxonomy_type', ['container', 'standard'])
                ->where('code', '!=', 'std_catalog')
                ->orderBy('_lft')
                ->get();

            return response()->json([
                'levels'    => $this->standardLevels($path),
                'selection' => $this->standardNode($selected),
            ]);
        }

        $root = Category::where('code', 'std_catalog')->firstOrFail();
        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : $root->id;

        return response()->json([
            'options' => Category::query()
                ->where('parent_id', $parentId)
                ->where('taxonomy_type', 'standard')
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('source_path')
                ->get()
                ->map(fn (Category $category): array => $this->standardNode($category))
                ->values(),
        ]);
    }

    protected function platformOptions(array $data): JsonResponse
    {
        $platform = strtolower($data['platform'] ?? 'tiktok');
        $region = strtoupper($data['region'] ?? 'MY');
        $taxonomy = PlatformTaxonomy::query()
            ->where('platform', $platform)
            ->where('status', 'active')
            ->orderByRaw('CASE WHEN region = ? THEN 0 WHEN region = ? THEN 1 ELSE 2 END', [$region, 'MY'])
            ->latest('synced_at')
            ->firstOrFail();

        $selected = ! empty($data['selected'])
            ? PlatformCategory::query()
                ->where('platform_taxonomy_id', $taxonomy->id)
                ->where('external_id', $data['selected'])
                ->first()
            : null;

        if ($selected) {
            $path = collect();
            $cursor = $selected;
            while ($cursor) {
                $path->prepend($cursor);
                $cursor = $cursor->parent;
            }

            return response()->json([
                'levels'    => $this->platformLevels($path, $taxonomy),
                'selection' => $this->platformNode($selected, $taxonomy),
                'taxonomy'  => $this->taxonomyPayload($taxonomy),
            ]);
        }

        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;

        return response()->json([
            'options' => PlatformCategory::query()
                ->where('platform_taxonomy_id', $taxonomy->id)
                ->where('parent_id', $parentId)
                ->orderBy('name')
                ->get()
                ->map(fn (PlatformCategory $category): array => $this->platformNode($category, $taxonomy))
                ->values(),
            'taxonomy' => $this->taxonomyPayload($taxonomy),
        ]);
    }

    protected function standardLevels($path): array
    {
        $root = Category::where('code', 'std_catalog')->firstOrFail();
        $parentId = $root->id;
        $levels = [];
        foreach ($path as $selected) {
            $levels[] = [
                'selected_id' => $selected->id,
                'options'     => Category::query()
                    ->where('parent_id', $parentId)
                    ->where('taxonomy_type', 'standard')
                    ->where('status', 'active')
                    ->orderBy('sort_order')
                    ->orderBy('source_path')
                    ->get()
                    ->map(fn (Category $category): array => $this->standardNode($category))
                    ->values(),
            ];
            $parentId = $selected->id;
        }

        return $levels;
    }

    protected function platformLevels($path, PlatformTaxonomy $taxonomy): array
    {
        $parentId = null;
        $levels = [];
        foreach ($path as $selected) {
            $levels[] = [
                'selected_id' => $selected->id,
                'options'     => PlatformCategory::query()
                    ->where('platform_taxonomy_id', $taxonomy->id)
                    ->where('parent_id', $parentId)
                    ->orderBy('name')
                    ->get()
                    ->map(fn (PlatformCategory $category): array => $this->platformNode($category, $taxonomy))
                    ->values(),
            ];
            $parentId = $selected->id;
        }

        return $levels;
    }

    protected function standardNode(Category $category): array
    {
        return [
            'id'      => $category->id,
            'value'   => $category->code,
            'label'   => $category->name,
            'path'    => $category->source_path ?: $category->name,
            'is_leaf' => (bool) $category->is_assignable,
            'enabled' => $category->status === 'active',
        ];
    }

    protected function platformNode(PlatformCategory $category, PlatformTaxonomy $taxonomy): array
    {
        return [
            'id'       => $category->id,
            'value'    => $category->external_id,
            'label'    => $category->name,
            'path'     => $category->path,
            'is_leaf'  => (bool) $category->is_leaf,
            'enabled'  => (bool) $category->enabled,
            'version'  => $taxonomy->version,
            'platform' => $taxonomy->platform,
            'region'   => $taxonomy->region,
        ];
    }

    protected function taxonomyPayload(PlatformTaxonomy $taxonomy): array
    {
        return $taxonomy->only(['id', 'platform', 'region', 'version']);
    }
}
