<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Webkul\Admin\DataGrids\Catalog\ProductDataGrid;
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

it('flattens direct and grouped simple leaves in the configurable variations endpoint', function () {
    $this->loginAsAdmin();

    $parent = Product::factory()->create([
        'type'   => 'configurable',
        'sku'    => 'VARIATIONS-'.Str::upper(Str::random(8)),
        'values' => [
            'channel_locale_specific' => [
                'default' => ['en_US' => ['name' => 'Parent display name']],
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
        'values'    => ['common' => ['Inventory' => 7]],
    ]);
    $nested = Product::factory()->create([
        'type'      => 'simple',
        'parent_id' => $group->id,
        'sku'       => $parent->sku.'-NESTED',
        'values'    => ['common' => ['Inventory' => 9]],
    ]);

    $response = $this->getJson(route('admin.catalog.products.variations', $parent->id))
        ->assertOk()
        ->assertJsonPath('total', 2);

    $records = collect($response->json('records'));

    expect($records->pluck('id'))
        ->toContain($direct->id, $nested->id)
        ->and($records->firstWhere('id', $nested->id)['name'])->toBe('Parent display name')
        ->and($records->firstWhere('id', $direct->id)['stock'])->toBe(7)
        ->and($records->firstWhere('id', $direct->id)['image'])->toContain('product/test/parent-main.jpg')
        ->and($records->firstWhere('id', $direct->id)['image_inherited'])->toBeTrue();
});

it('renders the expandable variations component on the Products page', function () {
    $this->loginAsAdmin();

    $this->get(route('admin.catalog.products.index'))
        ->assertOk()
        ->assertSee('v-product-list-body-template', false)
        ->assertSee('<template v-if="isLoading">', false)
        ->assertDontSee('shimmer.datagrid.table.body :isMultiRow="true" v-if=', false)
        ->assertSee('gridTemplateColumns', false)
        ->assertSee('copySku(variation.sku)', false)
        ->assertSee('复制 SKU', false)
        ->assertSee('Variations / SKU')
        ->assertSee('Simple 商品不再作为顶层商品重复显示');
});
