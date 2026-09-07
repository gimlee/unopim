<?php

use Illuminate\Support\Str;
use Webkul\Category\Models\Category;
use Webkul\Category\Models\CategoryMapping;
use Webkul\Category\Models\CategoryClassificationRule;
use Webkul\Category\Models\PlatformCategory;
use Webkul\Category\Models\PlatformTaxonomy;
use Webkul\Category\Models\ProductCategoryAssignment;
use Webkul\Category\Models\ProductCategoryClassificationCache;
use Webkul\Category\Models\ProductPlatformCategoryAssignment;
use Webkul\MagicAI\Enums\AiProvider;
use Webkul\MagicAI\Models\MagicAIPlatform;
use Webkul\Product\Models\Product;
use Webkul\User\Models\Admin;

it('renders the separated Chinese taxonomy management pages', function () {
    $admin = Admin::query()->whereNotNull('role_id')->firstOrFail();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.catalog.taxonomy.index'))
        ->assertOk()
        ->assertSee('PIM 标准类目')
        ->assertSee('1688 类目节点')
        ->assertDontSee('待审核商品主类目');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.catalog.taxonomy.mappings.index'))
        ->assertRedirect(route('admin.catalog.taxonomy.mappings.source.index'));

    $this->actingAs($admin, 'admin')
        ->get(route('admin.catalog.taxonomy.mappings.source.index'))
        ->assertOk()
        ->assertSee('PIM 标准类目 ↔ 1688 类目')
        ->assertSee('搜索标准/1688 类目名称');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.catalog.taxonomy.mappings.platform.index', ['status' => 'rejected']))
        ->assertOk()
        ->assertSee('PIM 标准类目 ↔ 销售平台类目')
        ->assertSee('全部状态（含已拒绝）')
        ->assertSee('置信度：高到低')
        ->assertSee('每页')
        ->assertSee('跳至');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.catalog.taxonomy.mappings.form'))
        ->assertOk()
        ->assertSee('新增 / 更新平台映射');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.catalog.taxonomy.reviews.index'))
        ->assertOk()
        ->assertSee('商品主类目审核')
        ->assertSee('SKU / 商品名称')
        ->assertSee('完全无法分类');
});

it('loads standard and tiktok categories one level at a time', function () {
    $admin = Admin::query()->whereNotNull('role_id')->firstOrFail();
    $standard = Category::query()
        ->where('taxonomy_type', 'standard')
        ->where('is_assignable', true)
        ->where('status', 'active')
        ->where('code', '!=', 'std_uncategorized')
        ->firstOrFail();
    $platform = PlatformCategory::query()
        ->where('is_leaf', true)
        ->where('enabled', true)
        ->whereHas('taxonomy', fn ($query) => $query->where('platform', 'tiktok')->where('region', 'MY'))
        ->firstOrFail();

    $response = $this->actingAs($admin, 'admin')
        ->getJson(route('admin.catalog.taxonomy.selector.options', [
            'type'     => 'standard',
            'selected' => $standard->code,
        ]))
        ->assertOk()
        ->assertJsonPath('selection.value', $standard->code)
        ->assertJsonStructure(['levels' => [['selected_id', 'options']]]);

    foreach ($response->json('levels') as $level) {
        expect(collect($level['options'])->pluck('id'))->toContain($level['selected_id']);
    }

    $this->actingAs($admin, 'admin')
        ->getJson(route('admin.catalog.taxonomy.selector.options', [
            'type'     => 'platform',
            'platform' => 'tiktok',
            'region'   => 'MY',
            'selected' => $platform->external_id,
        ]))
        ->assertOk()
        ->assertJsonPath('selection.value', $platform->external_id)
        ->assertJsonPath('taxonomy.platform', 'tiktok');
});

it('lets an admin save PIM and TikTok categories from a product page', function () {
    $admin = Admin::query()->whereNotNull('role_id')->firstOrFail();
    $product = Product::query()->firstOrFail();
    $standard = Category::query()
        ->where('taxonomy_type', 'standard')
        ->where('is_assignable', true)
        ->where('status', 'active')
        ->where('code', '!=', 'std_uncategorized')
        ->firstOrFail();
    $platform = PlatformCategory::query()
        ->where('is_leaf', true)
        ->where('enabled', true)
        ->whereHas('taxonomy', fn ($query) => $query->where('platform', 'tiktok')->where('region', 'MY')->where('status', 'active'))
        ->firstOrFail();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.catalog.products.edit', $product->id))
        ->assertOk()
        ->assertSee('商品类目归属')
        ->assertSee('规则分类')
        ->assertSee('AI 分类')
        ->assertSee('v-taxonomy-cascader-template', false)
        ->assertSee("app.component('v-taxonomy-cascader'", false)
        ->assertSee('v-product-taxonomy-panel-template', false);

    $this->actingAs($admin, 'admin')
        ->putJson(route('admin.catalog.products.taxonomy.update', $product->id), [
            'standard_category_code'        => $standard->code,
            'platform'                      => 'tiktok',
            'platform_category_external_id' => $platform->external_id,
            'method'                        => 'manual',
        ])
        ->assertOk()
        ->assertJsonPath('standard_path', $standard->source_path ?: $standard->name)
        ->assertJsonPath('platform_path', $platform->path);

    expect(data_get($product->fresh()->values, 'categories.0'))->toBe($standard->code);
    $this->assertDatabaseHas((new ProductPlatformCategoryAssignment)->getTable(), [
        'product_id'           => $product->id,
        'platform'             => 'tiktok',
        'platform_category_id' => $platform->id,
        'status'               => 'confirmed',
    ]);
});

it('caches and applies rule classification independently from AI results', function () {
    $admin = Admin::query()->whereNotNull('role_id')->firstOrFail();
    $suffix = Str::lower(Str::random(10));
    $standard = Category::factory()->create([
        'code'            => "rule-category-{$suffix}",
        'taxonomy_type'   => 'standard',
        'is_assignable'   => true,
        'status'          => 'active',
        'source_path'     => '测试类目 > 极光投影灯',
        'additional_data' => ['locale_specific' => ['zh_CN' => ['name' => '极光投影灯']]],
    ]);
    CategoryClassificationRule::create([
        'category_id' => $standard->id,
        'rule_type'   => 'keyword',
        'field'       => 'title',
        'operator'    => 'contains',
        'value'       => "唯一规则词{$suffix}",
        'weight'      => 50,
        'status'      => true,
    ]);
    $taxonomy = PlatformTaxonomy::create([
        'code'     => "rule-tiktok-{$suffix}",
        'platform' => 'tiktok',
        'region'   => 'MY',
        'version'  => "test-{$suffix}",
        'status'   => 'active',
    ]);
    $platform = PlatformCategory::create([
        'platform_taxonomy_id' => $taxonomy->id,
        'external_id'          => "rule-platform-{$suffix}",
        'name'                 => '投影灯',
        'path'                 => '家居用品 > 灯具 > 投影灯',
        'is_leaf'              => true,
        'enabled'              => true,
    ]);
    CategoryMapping::create([
        'category_id'          => $standard->id,
        'platform_category_id' => $platform->id,
        'mapping_type'         => 'exact',
        'status'               => 'confirmed',
        'confidence'           => 1,
    ]);
    $product = Product::factory()->configurable()->create([
        'sku'    => "RULE-PRODUCT-{$suffix}",
        'values' => [
            'channel_locale_specific' => ['default' => ['zh_CN' => ['name' => "唯一规则词{$suffix} 商品"]]],
            'common'                  => ['source_attributes' => '灯光 投影'],
            'categories'              => [],
        ],
    ]);

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.catalog.products.taxonomy.rule-suggest', $product->id))
        ->assertOk()
        ->assertJsonPath('data.method', 'rule')
        ->assertJsonPath('data.standard.value', $standard->code)
        ->assertJsonPath('data.platform.value', $platform->external_id);

    $this->assertDatabaseHas((new ProductCategoryClassificationCache)->getTable(), [
        'product_id'           => $product->id,
        'method'               => 'rule',
        'standard_category_id' => $standard->id,
        'platform_category_id' => $platform->id,
    ]);

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.catalog.products.taxonomy.classification.apply', [$product->id, 'rule']))
        ->assertOk()
        ->assertJsonPath('data.status', 'applied');

    expect(data_get($product->fresh()->values, 'categories.0'))->toBe($standard->code)
        ->and(data_get($product->fresh()->values, 'common.category_classification_method'))->toBe('rule');
    $this->assertDatabaseHas((new ProductPlatformCategoryAssignment)->getTable(), [
        'product_id'           => $product->id,
        'platform_category_id' => $platform->id,
        'method'               => 'rule',
    ]);

    $this->actingAs($admin, 'admin')
        ->deleteJson(route('admin.catalog.products.taxonomy.classification.clear', [$product->id, 'rule']))
        ->assertOk();
    $this->assertDatabaseMissing((new ProductCategoryClassificationCache)->getTable(), [
        'product_id' => $product->id,
        'method'     => 'rule',
    ]);
    expect(data_get($product->fresh()->values, 'categories.0'))->toBe($standard->code);
});

it('uses Zhipu general API and excludes Coding Plan from product classification', function () {
    expect(AiProvider::Zhipu->defaultUrl())->toBe('https://open.bigmodel.cn/api/paas/v4')
        ->and(AiProvider::ZhipuCodePlan->defaultUrl())->toBe('https://open.bigmodel.cn/api/coding/paas/v4')
        ->and(AiProvider::ZhipuCodePlan->label())->toContain('Code Plan');

    $codingPlatform = MagicAIPlatform::create([
        'label'      => 'Zhipu Code Plan Test',
        'provider'   => AiProvider::ZhipuCodePlan->value,
        'api_url'    => AiProvider::ZhipuCodePlan->defaultUrl(),
        'api_key'    => 'not-a-real-key',
        'models'     => 'glm-5.3-flash,glm-5.3,glm-5.2',
        'is_default' => false,
        'status'     => true,
    ]);
    $generalPlatform = MagicAIPlatform::create([
        'label'      => 'Zhipu General API Test',
        'provider'   => AiProvider::Zhipu->value,
        'api_url'    => AiProvider::Zhipu->defaultUrl(),
        'api_key'    => 'not-a-real-key',
        'models'     => 'glm-5.3-flash',
        'is_default' => false,
        'status'     => true,
    ]);

    $admin = Admin::query()->whereNotNull('role_id')->firstOrFail();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.catalog.products.edit', Product::query()->firstOrFail()->id))
        ->assertOk()
        ->assertDontSee($codingPlatform->label)
        ->assertSee($generalPlatform->label)
        ->assertSee('glm-5.3-flash');
});

it('shows one review row for a configurable product and hides variant assignments', function () {
    $admin = Admin::query()->whereNotNull('role_id')->firstOrFail();
    $category = Category::query()
        ->where('taxonomy_type', 'standard')
        ->where('is_assignable', true)
        ->where('status', 'active')
        ->firstOrFail();
    $parent = Product::factory()->configurable()->create(['sku' => 'REVIEW-PARENT-'.Str::random(8)]);
    $variant = Product::factory()->simple()->create([
        'sku'       => 'REVIEW-VARIANT-'.Str::random(8),
        'parent_id' => $parent->id,
    ]);
    foreach ([$parent, $variant] as $product) {
        ProductCategoryAssignment::create([
            'product_id'  => $product->id,
            'category_id' => $category->id,
            'role'        => 'primary',
            'status'      => 'proposed',
            'method'      => 'rule',
        ]);
    }

    $this->actingAs($admin, 'admin')
        ->get(route('admin.catalog.taxonomy.reviews.index', ['search' => 'REVIEW-']))
        ->assertOk()
        ->assertSee($parent->sku)
        ->assertDontSee($variant->sku);
});

it('edits a pending platform mapping target and status', function () {
    $admin = Admin::query()->whereNotNull('role_id')->firstOrFail();
    $suffix = Str::lower(Str::random(10));
    $category = Category::factory()->create([
        'code'          => "mapping-edit-{$suffix}",
        'taxonomy_type' => 'standard',
        'is_assignable' => true,
        'status'        => 'active',
    ]);
    $taxonomy = PlatformTaxonomy::create([
        'code'     => "mapping-taxonomy-{$suffix}",
        'platform' => "platform-{$suffix}",
        'region'   => 'MY',
        'version'  => "version-{$suffix}",
        'status'   => 'active',
    ]);
    $platformCategory = PlatformCategory::create([
        'platform_taxonomy_id' => $taxonomy->id,
        'external_id'          => "old-{$suffix}",
        'name'                 => '旧类目',
        'path'                 => '旧类目',
        'is_leaf'              => true,
        'enabled'              => true,
    ]);
    $mapping = CategoryMapping::create([
        'category_id'          => $category->id,
        'platform_category_id' => $platformCategory->id,
        'mapping_type'         => 'conditional',
        'status'               => 'draft',
        'confidence'           => 0.5,
    ]);

    $this->actingAs($admin, 'admin')
        ->put(route('admin.catalog.taxonomy.mappings.update', $mapping->id), [
            'category_code' => $category->code,
            'platform'      => $taxonomy->platform,
            'region'        => 'MY',
            'external_id'   => "new-{$suffix}",
            'name'          => '新类目',
            'path'          => '一级 > 新类目',
            'version'       => $taxonomy->version,
            'mapping_type'  => 'exact',
            'status'        => 'confirmed',
        ])
        ->assertRedirect();

    $mapping->refresh();
    expect($mapping->status)->toBe('confirmed')
        ->and($mapping->mapping_type)->toBe('exact')
        ->and($mapping->platformCategory->external_id)->toBe("new-{$suffix}")
        ->and($mapping->platformCategory->path)->toBe('一级 > 新类目');
});
