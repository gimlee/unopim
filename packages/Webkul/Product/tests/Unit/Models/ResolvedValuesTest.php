<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Product\Models\Product;

uses(DatabaseTransactions::class);

it('exposes resolvedValues on the product model, inheriting from the parent', function () {
    $parent = Product::factory()->configurable()->create([
        'values' => ['common' => ['brand' => 'Nike', 'material' => 'Cotton']],
    ]);

    $variant = Product::factory()->create([
        'parent_id' => $parent->id,
        'values'    => ['common' => ['size' => 'S', 'sku' => $parent->sku.'-S']],
    ]);

    expect($variant->resolvedValues()['common'])->toMatchArray([
        'brand'    => 'Nike',
        'material' => 'Cotton',
        'size'     => 'S',
    ]);
});

it('returns own values for a product without a parent', function () {
    $simple = Product::factory()->create([
        'values' => ['common' => ['sku' => 'SOLO', 'name' => 'Solo']],
    ]);

    expect($simple->resolvedValues()['common'])->toMatchArray(['sku' => 'SOLO', 'name' => 'Solo']);
});

it('resolves a localized display name and falls back to the sku', function () {
    $localized = Product::factory()->create([
        'sku'    => 'DISPLAY-NAME-SKU',
        'values' => [
            'channel_locale_specific' => [
                'default' => ['zh_CN' => ['name' => '中文商品名称']],
            ],
        ],
    ]);
    $withoutName = Product::factory()->create([
        'sku'    => 'FALLBACK-SKU',
        'values' => ['common' => []],
    ]);

    expect($localized->displayName('default', 'zh_CN'))->toBe('中文商品名称')
        ->and($withoutName->displayName('default', 'zh_CN'))->toBe('FALLBACK-SKU');
});
