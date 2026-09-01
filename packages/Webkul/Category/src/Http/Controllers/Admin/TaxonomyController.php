<?php

namespace Webkul\Category\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Webkul\Category\Models\Category;
use Webkul\Category\Models\CategoryMapping;
use Webkul\Category\Models\PlatformCategory;
use Webkul\Category\Models\PlatformTaxonomy;
use Webkul\Category\Models\ProductCategoryAssignment;

class TaxonomyController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $categoryQuery = Category::query()
            ->where('taxonomy_type', 'standard')
            ->where('is_assignable', true)
            ->withCount(['aliases', 'mappings'])
            ->orderBy('source_path');
        if ($search !== '') {
            $categoryQuery->where(function ($query) use ($search): void {
                $query->where('code', 'ilike', "%{$search}%")
                    ->orWhere('source_path', 'ilike', "%{$search}%");
            });
        }

        return view('category::taxonomy.index', [
            'categories' => $categoryQuery->paginate(50)->withQueryString(),
            'taxonomies' => PlatformTaxonomy::withCount('categories')->orderBy('platform')->orderBy('region')->get(),
            'mappings' => CategoryMapping::with(['category', 'platformCategory.taxonomy'])->latest()->limit(100)->get(),
            'assignments' => ProductCategoryAssignment::with(['product', 'category'])
                ->where('status', 'proposed')->latest()->limit(100)->get(),
            'stats' => [
                'standard' => Category::where('taxonomy_type', 'standard')->count(),
                'assignable' => Category::where('taxonomy_type', 'standard')->where('is_assignable', true)->count(),
                'mappings' => CategoryMapping::where('status', 'confirmed')->count(),
                'reviews' => ProductCategoryAssignment::where('status', 'proposed')->count(),
            ],
        ]);
    }

    public function storeMapping(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category_code' => ['required', 'string', 'exists:categories,code'],
            'platform' => ['required', 'string', 'max:64'],
            'region' => ['required', 'string', 'max:16'],
            'external_id' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'path' => ['required', 'string'],
            'version' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:draft,confirmed,rejected'],
            'mapping_type' => ['required', 'in:exact,broader,narrower,conditional'],
        ]);
        $platform = strtolower($data['platform']);
        $region = strtoupper($data['region']);
        $version = $data['version'] ?: 'current';
        $taxonomy = PlatformTaxonomy::updateOrCreate(
            ['code' => Str::snake("{$platform}_{$region}_{$version}")],
            ['platform' => $platform, 'region' => $region, 'version' => $version, 'status' => 'active', 'synced_at' => now()]
        );
        $platformCategory = PlatformCategory::updateOrCreate(
            ['platform_taxonomy_id' => $taxonomy->id, 'external_id' => $data['external_id']],
            ['name' => $data['name'], 'path' => $data['path'], 'is_leaf' => true, 'enabled' => true]
        );
        $category = Category::where('code', $data['category_code'])->firstOrFail();
        CategoryMapping::updateOrCreate(
            ['category_id' => $category->id, 'platform_category_id' => $platformCategory->id],
            [
                'mapping_type' => $data['mapping_type'],
                'status' => $data['status'],
                'confidence' => $data['status'] === 'confirmed' ? 1 : null,
                'reviewed_by' => $data['status'] === 'confirmed' ? auth()->guard('admin')->id() : null,
                'reviewed_at' => $data['status'] === 'confirmed' ? now() : null,
            ]
        );

        return back()->with('success', trans('category::app.taxonomy.mapping-saved'));
    }

    public function destroyMapping(int $id): RedirectResponse
    {
        CategoryMapping::findOrFail($id)->delete();

        return back()->with('success', trans('category::app.taxonomy.mapping-deleted'));
    }

    public function reviewAssignment(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:confirmed,rejected']]);
        $assignment = ProductCategoryAssignment::with(['product', 'category'])->findOrFail($id);
        $assignment->update([
            'status' => $data['status'],
            'reviewed_by' => auth()->guard('admin')->id(),
            'reviewed_at' => now(),
        ]);
        if ($data['status'] === 'confirmed') {
            ProductCategoryAssignment::where('product_id', $assignment->product_id)
                ->where('role', 'primary')
                ->where('id', '!=', $assignment->id)
                ->update(['status' => 'rejected', 'reviewed_at' => now()]);
            $values = $assignment->product->values ?: [];
            $values['categories'] = [$assignment->category->code];
            $assignment->product->values = $values;
            $assignment->product->save();
        }

        return back()->with('success', trans('category::app.taxonomy.assignment-reviewed'));
    }
}
