<?php

use Illuminate\Support\Str;
use Webkul\Category\Models\Category;
use Webkul\Category\Models\CategoryMapping;
use Webkul\Category\Models\PlatformCategory;
use Webkul\Category\Models\PlatformTaxonomy;
use Webkul\Category\Models\ProductCategoryAssignment;
use Webkul\Category\Services\TaxonomySynchronizer;
use Webkul\Product\Models\Product;

it('requires a confirmed product assignment when resolving a platform category for a sku', function () {
    $suffix = Str::lower(Str::random(10));
    $category = Category::factory()->create([
        'code' => "taxonomy-test-{$suffix}",
        'taxonomy_type' => 'standard',
        'is_assignable' => true,
        'status' => 'active',
    ]);
    $product = Product::factory()->create(['sku' => "TAX{$suffix}"]);
    $taxonomy = PlatformTaxonomy::create([
        'code' => "tiktok-test-{$suffix}",
        'platform' => "tiktok-test-{$suffix}",
        'region' => 'MY',
        'version' => 'test',
        'status' => 'active',
    ]);
    $platformCategory = PlatformCategory::create([
        'platform_taxonomy_id' => $taxonomy->id,
        'external_id' => "external-{$suffix}",
        'name' => 'Test category',
        'path' => 'Test > Category',
        'is_leaf' => true,
        'enabled' => true,
    ]);
    CategoryMapping::create([
        'category_id' => $category->id,
        'platform_category_id' => $platformCategory->id,
        'status' => 'confirmed',
        'priority' => 100,
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
        'product_id' => $product->id,
        'category_id' => $category->id,
        'role' => 'primary',
        'status' => 'proposed',
        'method' => 'rule',
        'confidence' => 0.7,
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
        'external_id' => $platformCategory->external_id,
        'path' => $platformCategory->path,
        'mapping_status' => 'confirmed',
    ]);
});

it('preserves manually locked category structure while refreshing source metadata', function () {
    $suffix = Str::lower(Str::random(10));
    $originalParent = Category::factory()->create(['code' => "original-parent-{$suffix}"]);
    $incomingParent = Category::factory()->create(['code' => "incoming-parent-{$suffix}"]);
    $category = Category::factory()->create([
        'code' => "locked-category-{$suffix}",
        'parent_id' => $originalParent->id,
        'taxonomy_type' => 'standard',
        'is_assignable' => true,
        'status' => 'active',
        'source_platform' => '1688',
        'source_path' => '旧来源路径',
        'source_url' => 'https://old.example.test',
        'sync_locked' => true,
        'additional_data' => [
            'locale_specific' => ['zh_CN' => ['name' => '人工标准类目名']],
        ],
    ]);

    resolve(TaxonomySynchronizer::class)->sync([
        'categories' => [[
            'code' => $category->code,
            'parent' => $incomingParent->code,
            'labels' => ['zh_CN' => '1688 新名称'],
            'taxonomy_type' => 'container',
            'is_assignable' => false,
            'status' => 'inactive',
            'sort_order' => 999,
            'source_platform' => '1688',
            'source_path' => '新来源路径',
            'source_url' => 'https://new.example.test',
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
