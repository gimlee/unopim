<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Webkul\Admin\DataGrids\Catalog\ProductDataGrid;
use Webkul\Attribute\Models\Attribute;
use Webkul\Attribute\Models\AttributeFamily;
use Webkul\Attribute\Models\AttributeGroup;
use Webkul\Category\Models\Category;
use Webkul\Category\Models\ProductCategoryAssignment;
use Webkul\Product\Models\Product;

uses(DatabaseTransactions::class);

beforeEach(fn () => config(['elasticsearch.enabled' => false]));

it('lists only root configurable products and hides their simple children from top-level rows', function () {
    $this->loginAsAdmin();

    $parent = Product::factory()->create([
        'type' => 'configurable',
        'sku'  => 'GROUPED-'.Str::upper(Str::random(8)),
    ]);
    $child = Product::factory()->create([
        'type'      => 'simple',
        'parent_id' => $parent->id,
        'sku'       => $parent->sku.'-RED',
    ]);

    request()->replace([
        'pagination' => ['page' => 1, 'per_page' => 50],
        'sort'       => ['column' => 'product_id', 'order' => 'desc'],
        'filters'    => ['sku' => [$parent->sku]],
    ]);

    $grid = app(ProductDataGrid::class);
    $grid->prepare();
    $records = collect(json_decode(json_encode($grid->formatData()['records']), true));

    expect($records->pluck('product_id'))
        ->toContain($parent->id)
        ->not->toContain($child->id);
});

it('shows the current category path and classification type for root products', function () {
    $this->loginAsAdmin();

    $product = Product::factory()->create([
        'type' => 'configurable',
        'sku'  => 'CATEGORY-GRID-'.Str::upper(Str::random(8)),
    ]);
    $category = Category::factory()->create([
        'code'          => 'category-grid-'.Str::lower(Str::random(8)),
        'taxonomy_type' => 'standard',
        'is_assignable' => true,
        'status'        => 'active',
        'source_path'   => '办公文化 > 办公文具 > 笔记本',
    ]);
    ProductCategoryAssignment::create([
        'product_id'  => $product->id,
        'category_id' => $category->id,
        'role'        => 'primary',
        'status'      => 'confirmed',
        'method'      => 'ai',
    ]);

    request()->replace([
        'pagination' => ['page' => 1, 'per_page' => 50],
        'sort'       => ['column' => 'product_id', 'order' => 'desc'],
        'filters'    => ['sku' => [$product->sku]],
    ]);

    $grid = app(ProductDataGrid::class);
    $grid->prepare();
    $record = collect(json_decode(json_encode($grid->formatData()['records']), true))
        ->firstWhere('product_id', $product->id);

    expect($record['primary_category'])->toContain('办公文化')
        ->and($record['taxonomy_method'])->toContain('AI 分类');
});

it('flattens direct and grouped simple leaves in the configurable variations endpoint', function () {
    $this->loginAsAdmin();

    $parent = Product::factory()->create([
        'type'   => 'configurable',
        'sku'    => 'VARIATIONS-'.Str::upper(Str::random(8)),
        'values' => [
            'channel_locale_specific' => [
                'default' => ['en_US' => [
                    'name'  => 'Parent display name',
                    'price' => ['CNY' => 999],
                ]],
            ],
            'common' => [
                'source_price_cny' => 15,
                'image'            => 'product/test/parent-main.jpg',
            ],
        ],
    ]);
    $group = Product::factory()->create([
        'type'      => 'variant_group',
        'parent_id' => $parent->id,
        'sku'       => $parent->sku.'-GROUP',
    ]);
    $direct = Product::factory()->create([
        'type'      => 'simple',
        'parent_id' => $parent->id,
        'sku'       => $parent->sku.'-DIRECT',
        'values'    => [
            'common'                  => ['Inventory' => 7],
            'channel_locale_specific' => [
                'default' => ['zh_CN' => ['price' => ['CNY' => 12.5, 'MYR' => 8.25]]],
            ],
        ],
    ]);
    $nested = Product::factory()->create([
        'type'      => 'simple',
        'parent_id' => $group->id,
        'sku'       => $parent->sku.'-NESTED',
        'values'    => [
            'common'                  => ['Inventory' => 9],
            'channel_locale_specific' => [
                'default' => ['zh_CN' => ['price' => ['CNY' => 20, 'MYR' => 13.2]]],
            ],
        ],
    ]);

    $response = $this->getJson(route('admin.catalog.products.variations', $parent->id))
        ->assertOk()
        ->assertJsonPath('total', 2);

    $records = collect($response->json('records'));

    expect($records->pluck('id'))
        ->toContain($direct->id, $nested->id)
        ->and($records->firstWhere('id', $nested->id)['name'])->toBe('Parent display name')
        ->and($records->firstWhere('id', $direct->id)['stock'])->toBe(7)
        ->and($records->firstWhere('id', $direct->id)['price'])->toBe('CNY 12.50 / MYR 8.25')
        ->and($records->firstWhere('id', $nested->id)['prices'])->toMatchArray(['CNY' => 20, 'MYR' => 13.2])
        ->and($records->firstWhere('id', $direct->id)['image'])->toContain('product/test/parent-main.jpg')
        ->and($records->firstWhere('id', $direct->id)['image_inherited'])->toBeTrue();
});

it('shows SKU price ranges and hides redundant classification diagnostics on configurable edit pages', function () {
    $this->loginAsAdmin();

    $family = AttributeFamily::factory()->create();
    $group = AttributeGroup::factory()->create(['code' => 'source_data_'.Str::lower(Str::random(6))]);
    $family->familyGroups()->attach($group);
    $mapping = $family->attributeFamilyGroupMappings()->first();

    $attributes = collect([
        ['code' => 'price', 'type' => 'price', 'label' => 'Price'],
        ['code' => 'short_description', 'type' => 'textarea', 'label' => 'Short Description'],
        ['code' => 'category_classification_status', 'type' => 'text', 'label' => 'Category Classification Status'],
        ['code' => 'category_classification_method', 'type' => 'text', 'label' => 'Category Classification Method'],
        ['code' => 'category_classification_confidence', 'type' => 'text', 'label' => 'Category Classification Confidence'],
        ['code' => 'category_classification_evidence', 'type' => 'textarea', 'label' => 'Category Classification Evidence'],
        ['code' => 'meta_description', 'type' => 'textarea', 'label' => 'Meta Description'],
    ])->map(function (array $definition, int $position) use ($mapping): Attribute {
        $attribute = Attribute::query()->where('code', $definition['code'])->first()
            ?? Attribute::factory()->create([
                'code' => $definition['code'],
                'type' => $definition['type'],
            ]);
        $attribute->translateOrNew('en_US')->name = $definition['label'];
        $attribute->save();
        $mapping->customAttributes()->attach($attribute, ['position' => $position + 1]);

        return $attribute;
    });

    $parent = Product::factory()->create([
        'type'                => 'configurable',
        'attribute_family_id' => $family->id,
        'sku'                 => 'PRICE-SUMMARY-'.Str::upper(Str::random(8)),
        'values'              => [
            'channel_locale_specific' => [
                'default' => ['en_US' => ['price' => ['CNY' => 999]]],
            ],
            'common' => [
                'category_classification_status'     => 'classified',
                'category_classification_method'     => 'ai',
                'category_classification_confidence' => '0.99',
                'category_classification_evidence'   => 'hidden evidence',
            ],
        ],
    ]);

    foreach ([12.5, 20] as $index => $amount) {
        Product::factory()->create([
            'type'                => 'simple',
            'attribute_family_id' => $family->id,
            'parent_id'           => $parent->id,
            'sku'                 => $parent->sku.'-'.($index + 1),
            'values'              => [
                'channel_locale_specific' => [
                    'default' => ['zh_CN' => ['price' => ['CNY' => $amount]]],
                ],
            ],
        ]);
    }

    $response = $this->get(route('admin.catalog.products.edit', $parent->id))->assertOk();
    $content = $response->getContent();

    expect($attributes)->toHaveCount(7)
        ->and(str_contains($content, 'SKU 价格'))->toBeTrue()
        ->and(str_contains($content, 'CNY(人民币)'))->toBeTrue()
        ->and(str_contains($content, '12.50'))->toBeTrue()
        ->and(str_contains($content, '20.00'))->toBeTrue()
        ->and(str_contains($content, $parent->sku.'-1'))->toBeTrue()
        ->and(str_contains($content, $parent->sku.'-2'))->toBeTrue()
        ->and(str_contains($content, 'Category Classification Status'))->toBeFalse()
        ->and(str_contains($content, 'Category Classification Method'))->toBeFalse()
        ->and(str_contains($content, 'Category Classification Confidence'))->toBeFalse()
        ->and(str_contains($content, 'Category Classification Evidence'))->toBeFalse()
        ->and(str_contains($content, 'Meta Description'))->toBeFalse()
        ->and(str_contains($content, '商品描述优化记录'))->toBeTrue()
        ->and(str_contains($content, 'changedFields(revision)'))->toBeTrue()
        ->and(str_contains($content, 'whitespace-pre-line'))->toBeTrue()
        ->and(str_contains($content, '商品描述优化模板'))->toBeTrue()
        ->and(str_contains($content, 'AI商品描述'))->toBeTrue()
        ->and(str_contains($content, 'product-description-ai:optimize'))->toBeTrue()
        ->and(str_contains($content, 'name="values[channel_locale_specific][default][en_US][price]'))->toBeFalse();
});

it('renders the expandable variations component on the Products page', function () {
    $this->loginAsAdmin();

    $this->get(route('admin.catalog.products.index'))
        ->assertOk()
        ->assertSee('v-product-list-body-template', false)
        ->assertSee('<template v-if="isLoading">', false)
        ->assertDontSee('shimmer.datagrid.table.body :isMultiRow="true" v-if=', false)
        ->assertSee('gridTemplateColumns', false)
        ->assertSee('text-xs font-medium tabular-nums tracking-tight', false)
        ->assertSee('gap-0.5 select-none', false)
        ->assertSee('copySku(variation.sku)', false)
        ->assertSee('复制 SKU', false)
        ->assertSee('Variations / SKU')
        ->assertSee('Simple 商品不再作为顶层商品重复显示');
});
