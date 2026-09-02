<?php

namespace Webkul\Category\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Category\Models\Category;
use Webkul\Category\Models\CategoryMapping;
use Webkul\Category\Models\CategorySourceMapping;
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
            'categories' => $categoryQuery->paginate($this->perPage($request))->withQueryString(),
            'stats'      => [
                'standard'   => Category::where('taxonomy_type', 'standard')->count(),
                'assignable' => Category::where('taxonomy_type', 'standard')->where('is_assignable', true)->count(),
                'source1688' => Category::where('taxonomy_type', 'source')->where('source_platform', '1688')->count(),
                'tiktokMy'   => Category::where('taxonomy_type', 'platform')->where('source_platform', 'tiktok')->count(),
            ],
        ]);
    }

    public function mappings(): RedirectResponse
    {
        return redirect()->route('admin.catalog.taxonomy.mappings.source.index');
    }

    public function sourceMappings(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $query = CategorySourceMapping::with(['category', 'sourceCategory'])
            ->orderBy('source_platform')
            ->orderByDesc('id');
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->whereHas('category', fn ($category) => $category->where(
                    fn ($fields) => $fields
                        ->where('code', 'ilike', "%{$search}%")
                        ->orWhere('source_path', 'ilike', "%{$search}%")
                ))->orWhereHas('sourceCategory', fn ($category) => $category->where(
                    fn ($fields) => $fields
                        ->where('code', 'ilike', "%{$search}%")
                        ->orWhere('source_path', 'ilike', "%{$search}%")
                ));
            });
        }

        return view('category::taxonomy.source-mappings', [
            'sourceMappings' => $query->paginate($this->perPage($request))->withQueryString(),
            'stats'          => [
                'confirmed' => CategorySourceMapping::where('status', 'confirmed')->count(),
                'draft'     => CategorySourceMapping::where('status', 'draft')->count(),
                'rejected'  => CategorySourceMapping::where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function platformMappings(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $platform = strtolower(trim((string) $request->query('platform', '')));
        $region = strtoupper(trim((string) $request->query('region', '')));
        $mappingType = trim((string) $request->query('mapping_type', ''));
        $sort = trim((string) $request->query('sort', 'confidence_desc'));
        $query = CategoryMapping::with(['category', 'platformCategory.taxonomy']);
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($mappingType !== '') {
            $query->where('mapping_type', $mappingType);
        }
        if ($platform !== '' || $region !== '') {
            $query->whereHas('platformCategory.taxonomy', function ($taxonomy) use ($platform, $region): void {
                if ($platform !== '') {
                    $taxonomy->where('platform', $platform);
                }
                if ($region !== '') {
                    $taxonomy->where('region', $region);
                }
            });
        }
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->whereHas('category', fn ($category) => $category->where(
                    fn ($fields) => $fields
                        ->where('code', 'ilike', "%{$search}%")
                        ->orWhere('source_path', 'ilike', "%{$search}%")
                ))->orWhereHas('platformCategory', fn ($category) => $category->where(
                    fn ($fields) => $fields
                        ->where('external_id', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('path', 'ilike', "%{$search}%")
                ));
            });
        }
        match ($sort) {
            'confidence_asc' => $query->orderByRaw('confidence ASC NULLS LAST')->latest('id'),
            'latest'         => $query->latest(),
            default          => $query->orderByRaw('confidence DESC NULLS LAST')->latest('id'),
        };

        return view('category::taxonomy.platform-mappings', [
            'taxonomies' => PlatformTaxonomy::withCount('categories')->orderBy('platform')->orderBy('region')->get(),
            'mappings'   => $query->paginate($this->perPage($request))->withQueryString(),
            'platforms'  => PlatformTaxonomy::query()->distinct()->orderBy('platform')->pluck('platform'),
            'regions'    => PlatformTaxonomy::query()->whereNotNull('region')->distinct()->orderBy('region')->pluck('region'),
            'stats'      => [
                'confirmed' => CategoryMapping::where('status', 'confirmed')->count(),
                'draft'     => CategoryMapping::where('status', 'draft')->count(),
                'rejected'  => CategoryMapping::where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function mappingForm(): View
    {
        return view('category::taxonomy.mapping-form', [
            'taxonomies' => PlatformTaxonomy::withCount('categories')->orderBy('platform')->orderBy('region')->get(),
        ]);
    }

    public function reviews(Request $request): View
    {
        $status = trim((string) $request->query('status', 'proposed'));
        $type = trim((string) $request->query('type', ''));
        $search = trim((string) $request->query('search', ''));
        $query = ProductCategoryAssignment::with(['product', 'category'])->latest();
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($type === 'uncategorized') {
            $query->whereHas('category', fn ($category) => $category->where('code', 'std_uncategorized'));
        } elseif ($type === 'candidate') {
            $query->whereHas('category', fn ($category) => $category->where('code', '!=', 'std_uncategorized'));
        }
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->whereHas('product', fn ($product) => $product->where('sku', 'ilike', "%{$search}%"))
                    ->orWhereHas('category', fn ($category) => $category->where(
                        fn ($fields) => $fields
                            ->where('code', 'ilike', "%{$search}%")
                            ->orWhere('source_path', 'ilike', "%{$search}%")
                    ));
            });
        }

        return view('category::taxonomy.reviews', [
            'assignments' => $query->paginate($this->perPage($request, 100))->withQueryString(),
            'stats'       => [
                'proposed'      => ProductCategoryAssignment::where('status', 'proposed')->count(),
                'confirmed'     => ProductCategoryAssignment::where('status', 'confirmed')->count(),
                'rejected'      => ProductCategoryAssignment::where('status', 'rejected')->count(),
                'uncategorized' => ProductCategoryAssignment::where('status', 'proposed')
                    ->whereHas('category', fn ($category) => $category->where('code', 'std_uncategorized'))
                    ->count(),
            ],
        ]);
    }

    public function storeMapping(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category_code' => ['required', 'string', 'exists:categories,code'],
            'platform'      => ['required', 'string', 'max:64'],
            'region'        => ['required', 'string', 'max:16'],
            'external_id'   => ['required', 'string', 'max:255'],
            'name'          => ['required', 'string', 'max:255'],
            'path'          => ['required', 'string'],
            'version'       => ['nullable', 'string', 'max:255'],
            'status'        => ['required', 'in:draft,confirmed,rejected'],
            'mapping_type'  => ['required', 'in:exact,broader,narrower,conditional'],
        ]);
        $this->saveMappingData($data);

        return redirect()->route('admin.catalog.taxonomy.mappings.platform.index')
            ->with('success', trans('category::app.taxonomy.mapping-saved'));
    }

    public function updateMapping(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'category_code' => ['required', 'string', 'exists:categories,code'],
            'platform'      => ['required', 'string', 'max:64'],
            'region'        => ['required', 'string', 'max:16'],
            'external_id'   => ['required', 'string', 'max:255'],
            'name'          => ['required', 'string', 'max:255'],
            'path'          => ['required', 'string'],
            'version'       => ['nullable', 'string', 'max:255'],
            'status'        => ['required', 'in:draft,confirmed,rejected'],
            'mapping_type'  => ['required', 'in:exact,broader,narrower,conditional'],
        ]);
        $mapping = CategoryMapping::findOrFail($id);
        $this->saveMappingData($data, $mapping);

        return back()->with('success', trans('category::app.taxonomy.mapping-saved'));
    }

    public function destroyMapping(int $id): RedirectResponse
    {
        CategoryMapping::findOrFail($id)->delete();

        return back()->with('success', trans('category::app.taxonomy.mapping-deleted'));
    }

    public function reviewMapping(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:confirmed,rejected,draft']]);
        CategoryMapping::findOrFail($id)->update([
            'status'      => $data['status'],
            'reviewed_by' => $data['status'] === 'draft' ? null : auth()->guard('admin')->id(),
            'reviewed_at' => $data['status'] === 'draft' ? null : now(),
        ]);

        return back()->with('success', trans('category::app.taxonomy.mapping-saved'));
    }

    public function reviewAssignment(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'status'        => ['required', 'in:confirmed,rejected'],
            'category_code' => ['nullable', 'string', 'exists:categories,code'],
        ]);
        DB::transaction(function () use ($data, $id): void {
            $assignment = ProductCategoryAssignment::with(['product', 'category'])->findOrFail($id);
            if ($data['status'] === 'rejected') {
                $assignment->update([
                    'status'      => 'rejected',
                    'reviewed_by' => auth()->guard('admin')->id(),
                    'reviewed_at' => now(),
                ]);

                return;
            }

            $categoryCode = trim((string) ($data['category_code'] ?? $assignment->category->code));
            $category = Category::query()
                ->where('code', $categoryCode)
                ->where('taxonomy_type', 'standard')
                ->where('is_assignable', true)
                ->where('status', 'active')
                ->first();
            if (! $category || $category->code === 'std_uncategorized') {
                throw ValidationException::withMessages([
                    'category_code' => '确认商品主类目前，必须选择一个有效的 PIM 标准叶子类目。',
                ]);
            }
            $confirmed = ProductCategoryAssignment::updateOrCreate(
                [
                    'product_id'  => $assignment->product_id,
                    'category_id' => $category->id,
                    'role'        => 'primary',
                ],
                [
                    'status'     => 'confirmed',
                    'method'     => $category->id === $assignment->category_id ? $assignment->method : 'manual',
                    'confidence' => 1,
                    'evidence'   => $category->id === $assignment->category_id
                        ? $assignment->evidence
                        : ['人工审核修改类目'],
                    'taxonomy_version' => $assignment->taxonomy_version,
                    'reviewed_by'      => auth()->guard('admin')->id(),
                    'reviewed_at'      => now(),
                ]
            );
            ProductCategoryAssignment::where('product_id', $assignment->product_id)
                ->where('role', 'primary')
                ->where('id', '!=', $confirmed->id)
                ->update([
                    'status'      => 'rejected',
                    'reviewed_by' => auth()->guard('admin')->id(),
                    'reviewed_at' => now(),
                ]);
            $values = $assignment->product->values ?: [];
            $values['categories'] = [$category->code];
            $assignment->product->values = $values;
            $assignment->product->save();
        });

        return back()->with('success', trans('category::app.taxonomy.assignment-reviewed'));
    }

    protected function saveMappingData(array $data, ?CategoryMapping $mapping = null): CategoryMapping
    {
        return DB::transaction(function () use ($data, $mapping): CategoryMapping {
            $platform = strtolower($data['platform']);
            $region = strtoupper($data['region']);
            $version = $data['version'] ?: 'current';
            $taxonomy = $version === 'current'
                ? PlatformTaxonomy::query()
                    ->where('platform', $platform)
                    ->where('region', $region)
                    ->where('status', 'active')
                    ->latest('synced_at')
                    ->first()
                : PlatformTaxonomy::query()
                    ->where('platform', $platform)
                    ->where('region', $region)
                    ->where('version', $version)
                    ->first();
            $taxonomy ??= PlatformTaxonomy::updateOrCreate(
                ['code' => Str::snake("{$platform}_{$region}_{$version}")],
                ['platform' => $platform, 'region' => $region, 'version' => $version, 'status' => 'active', 'synced_at' => now()]
            );
            $platformCategory = PlatformCategory::firstOrNew([
                'platform_taxonomy_id' => $taxonomy->id,
                'external_id'          => $data['external_id'],
            ]);
            $platformCategory->fill([
                'name'    => $data['name'],
                'path'    => $data['path'],
                'is_leaf' => true,
            ]);
            if (! $platformCategory->exists) {
                $platformCategory->enabled = true;
            }
            $platformCategory->save();

            $category = Category::where('code', $data['category_code'])->firstOrFail();
            if ($category->taxonomy_type !== 'standard' || ! $category->is_assignable) {
                throw ValidationException::withMessages([
                    'category_code' => '只能选择可分配的 PIM 标准叶子类目。',
                ]);
            }
            $target = CategoryMapping::firstOrNew([
                'category_id'          => $category->id,
                'platform_category_id' => $platformCategory->id,
            ]);
            if ($mapping && $target->exists && $target->id !== $mapping->id) {
                $mapping->delete();
                $mapping = $target;
            } elseif ($mapping) {
                $mapping->category_id = $category->id;
                $mapping->platform_category_id = $platformCategory->id;
            } else {
                $mapping = $target;
            }
            $mapping->fill([
                'mapping_type' => $data['mapping_type'],
                'status'       => $data['status'],
                'confidence'   => $data['status'] === 'confirmed' ? 1 : $mapping->confidence,
                'reviewed_by'  => $data['status'] === 'draft' ? null : auth()->guard('admin')->id(),
                'reviewed_at'  => $data['status'] === 'draft' ? null : now(),
            ]);
            $mapping->save();

            return $mapping;
        });
    }

    protected function perPage(Request $request, int $default = 50): int
    {
        $perPage = (int) $request->query('per_page', $default);

        return in_array($perPage, [20, 50, 100, 200], true) ? $perPage : $default;
    }
}
