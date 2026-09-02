<?php

use Illuminate\Support\Str;
use Webkul\Category\Models\Category;
use Webkul\Category\Models\CategoryMapping;
use Webkul\Category\Models\PlatformCategory;
use Webkul\Category\Models\PlatformTaxonomy;
use Webkul\Category\Models\ProductCategoryAssignment;
use Webkul\Category\Models\ProductPlatformCategoryAssignment;
use Webkul\Category\Services\TaxonomySynchronizer;
use Webkul\Product\Models\Product;

it('requires a confirmed product assignment when resolving a platform category for a sku', function () {
    $suffix = Str::lower(Str::random(10));
    $category = Category::factory()->create([
        'code'          => "taxonomy-test-{$suffix}",
        'taxonomy_type' => 'standard',
        'is_assignable' => true,
        'status'        => 'active',
    ]);
    $product = Product::factory()->create(['sku' => "TAX{$suffix}"]);
    $taxonomy = PlatformTaxonomy::create([
        'code'     => "tiktok-test-{$suffix}",
        'platform' => "tiktok-test-{$suffix}",
        'region'   => 'MY',
        'version'  => 'test',
        'status'   => 'active',
    ]);
    $platformCategory = PlatformCategory::create([
        'platform_taxonomy_id' => $taxonomy->id,
        'external_id'          => "external-{$suffix}",
        'name'                 => 'Test category',
        'path'                 => 'Test > Category',
        'is_leaf'              => true,
        'enabled'              => true,
    ]);
    CategoryMapping::create([
        'category_id'          => $category->id,
        'platform_category_id' => $platformCategory->id,
        'status'               => 'confirmed',
        'priority'             => 100,
    ]);

    $synchronizer = resolve(TaxonomySynchronizer::class);

    expect($synchronizer->resolve(
        $category->code,
        $taxonomy->platform,
        'MY'
    ))->not->toBeNull();
    expect($synchronizer->resolve(
        $category->code,
        $taxonomy->platform,
        'MY',
        $product->sku
    ))->toBeNull();

    $assignment = ProductCategoryAssignment::create([
        'product_id'  => $product->id,
        'category_id' => $category->id,
        'role'        => 'primary',
        'status'      => 'proposed',
        'method'      => 'rule',
        'confidence'  => 0.7,
    ]);

    expect($synchronizer->resolve(
        $category->code,
        $taxonomy->platform,
        'MY',
        $product->sku
    ))->toBeNull();

    $assignment->update(['status' => 'confirmed']);

    expect($synchronizer->resolve(
        $category->code,
        $taxonomy->platform,
        'MY',
        $product->sku
    ))->toMatchArray([
        'canonical_code' => $category->code,
        'external_id'    => $platformCategory->external_id,
        'path'           => $platformCategory->path,
        'mapping_status' => 'confirmed',
    ]);
});

it('prefers a confirmed product platform category over the standard mapping', function () {
    $suffix = Str::lower(Str::random(10));
    $category = Category::factory()->create([
        'code'          => "override-standard-{$suffix}",
        'taxonomy_type' => 'standard',
        'is_assignable' => true,
        'status'        => 'active',
    ]);
    $product = Product::factory()->create(['sku' => "OVERRIDE{$suffix}"]);
    $taxonomy = PlatformTaxonomy::create([
        'code'     => "override-taxonomy-{$suffix}",
        'platform' => 'tiktok',
        'region'   => 'MY',
        'version'  => "test-{$suffix}",
        'status'   => 'active',
    ]);
    $platformCategory = PlatformCategory::create([
        'platform_taxonomy_id' => $taxonomy->id,
        'external_id'          => "override-external-{$suffix}",
        'name'                 => 'Product override',
        'path'                 => 'Override > Product',
        'is_leaf'              => true,
        'enabled'              => true,
    ]);
    ProductCategoryAssignment::create([
        'product_id'  => $product->id,
        'category_id' => $category->id,
        'role'        => 'primary',
        'status'      => 'confirmed',
        'method'      => 'manual',
    ]);
    ProductPlatformCategoryAssignment::create([
        'product_id'           => $product->id,
        'platform'             => 'tiktok',
        'platform_category_id' => $platformCategory->id,
        'status'               => 'confirmed',
        'method'               => 'manual',
    ]);

    expect(resolve(TaxonomySynchronizer::class)->resolve(
        $category->code,
        'tiktok',
        'TH',
        $product->sku
    ))->toMatchArray([
        'external_id'    => $platformCategory->external_id,
        'path'           => $platformCategory->path,
        'region'         => 'TH',
        'mapping_source' => 'product_override',
    ]);
});

it('preserves manually locked category structure while refreshing source metadata', function () {
    $suffix = Str::lower(Str::random(10));
    $originalParent = Category::factory()->create(['code' => "original-parent-{$suffix}"]);
    $incomingParent = Category::factory()->create(['code' => "incoming-parent-{$suffix}"]);
    $category = Category::factory()->create([
        'code'            => "locked-category-{$suffix}",
        'parent_id'       => $originalParent->id,
        'taxonomy_type'   => 'standard',
        'is_assignable'   => true,
        'status'          => 'active',
        'source_platform' => '1688',
        'source_path'     => '旧来源路径',
        'source_url'      => 'https://old.example.test',
        'sync_locked'     => true,
        'additional_data' => [
            'locale_specific' => ['zh_CN' => ['name' => '人工标准类目名']],
        ],
    ]);

    resolve(TaxonomySynchronizer::class)->sync([
        'categories' => [[
            'code'            => $category->code,
            'parent'          => $incomingParent->code,
            'labels'          => ['zh_CN' => '1688 新名称'],
            'taxonomy_type'   => 'container',
            'is_assignable'   => false,
            'status'          => 'inactive',
            'sort_order'      => 999,
            'source_platform' => '1688',
            'source_path'     => '新来源路径',
            'source_url'      => 'https://new.example.test',
        ]],
        'fix_tree' => false,
    ]);

    $category->refresh();

    expect($category->parent_id)->toBe($originalParent->id)
        ->and($category->additional_data['locale_specific']['zh_CN']['name'])->toBe('人工标准类目名')
        ->and($category->taxonomy_type)->toBe('standard')
        ->and($category->is_assignable)->toBeTrue()
        ->and($category->status)->toBe('active')
        ->and($category->source_path)->toBe('新来源路径')
        ->and($category->source_url)->toBe('https://new.example.test');
});

it('moves product json categories before removing legacy categories', function () {
    $suffix = Str::lower(Str::random(10));
    $legacy = Category::factory()->create([
        'code'          => "legacy-remove-{$suffix}",
        'taxonomy_type' => 'legacy',
        'is_assignable' => true,
        'status'        => 'deprecated',
    ]);
    $product = Product::factory()->create([
        'sku'    => "REM{$suffix}",
        'values' => ['categories' => ['root', $legacy->code]],
    ]);

    $report = resolve(TaxonomySynchronizer::class)->sync([
        'remove_categories' => [$legacy->code],
        'fallback_category' => 'std_uncategorized',
    ]);

    $product->refresh();

    expect(Category::where('code', $legacy->code)->exists())->toBeFalse()
        ->and($product->values['categories'])->toBe(['std_uncategorized'])
        ->and(ProductCategoryAssignment::query()
            ->where('product_id', $product->id)
            ->whereHas('category', fn ($query) => $query->where('code', 'std_uncategorized'))
            ->where('status', 'proposed')
            ->exists())->toBeTrue()
        ->and($report['removed_categories']['removed'])->toBe([$legacy->code])
        ->and($report['removed_categories']['updated_products'])->toBe(1);
});

it('syncs a confirmed source category mapping without making source nodes assignable', function () {
    $suffix = Str::lower(Str::random(10));
    $standard = Category::factory()->create([
        'code'          => "standard-source-map-{$suffix}",
        'taxonomy_type' => 'standard',
        'is_assignable' => true,
    ]);
    $source = Category::factory()->create([
        'code'            => "source-map-{$suffix}",
        'taxonomy_type'   => 'source',
        'is_assignable'   => false,
        'source_platform' => '1688',
    ]);

    $report = resolve(TaxonomySynchronizer::class)->sync([
        'source_mappings' => [[
            'category_code'        => $standard->code,
            'source_category_code' => $source->code,
            'source_platform'      => '1688',
            'mapping_type'         => 'exact',
            'status'               => 'confirmed',
            'confidence'           => 1,
        ]],
    ]);

    expect($standard->sourceMappings()->where('source_category_id', $source->id)->where('status', 'confirmed')->exists())->toBeTrue()
        ->and($report['source_mappings']['created'])->toBe(1);
});
