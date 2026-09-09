<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Webkul\Admin\DataGrids\Catalog\ProductDataGrid;
use Webkul\Product\Models\Product;

uses(DatabaseTransactions::class);

function insertListingHistory(Product $product, array $overrides = []): int
{
    return DB::table('product_listing_histories')->insertGetId(array_merge([
        'product_id'   => $product->id,
        'sku'          => $product->sku,
        'platform'     => 'tiktok',
        'region'       => 'MY',
        'attempt_id'   => 'attempt-'.uniqid(),
        'listing_type' => 'draft',
        'status'       => 'saved',
        'published'    => false,
        'started_at'   => now()->subMinute(),
        'completed_at' => now(),
        'created_at'   => now(),
        'updated_at'   => now(),
    ], $overrides));
}

it('shows and refreshes listing history in newest-first order', function () {
    $this->loginAsAdmin();
    $product = Product::factory()->configurable()->create(['sku' => 'LISTING-DRAWER-'.uniqid()]);
    insertListingHistory($product, [
        'attempt_id'   => 'attempt-old',
        'region'       => 'MY',
        'listing_type' => 'draft',
        'status'       => 'saved',
        'completed_at' => now()->subMinutes(10),
    ]);
    insertListingHistory($product, [
        'attempt_id'   => 'attempt-new',
        'region'       => 'TH',
        'listing_type' => 'review',
        'status'       => 'submitted',
        'completed_at' => now(),
    ]);

    $this->get(route('admin.catalog.products.edit', $product->id))
        ->assertOk()
        ->assertSee('上架历史')
        ->assertViewHas('listingHistories', function ($histories): bool {
            return $histories->count() === 2 && $histories->first()->attempt_id === 'attempt-new';
        });

    $this->getJson(route('admin.catalog.products.listing_history.index', $product->id))
        ->assertOk()
        ->assertJsonPath('data.0.attempt_id', 'attempt-new')
        ->assertJsonPath('data.0.listing_type', 'review')
        ->assertJsonPath('data.1.attempt_id', 'attempt-old');
});

it('renders only the latest listing result without duplicating the product grid row', function () {
    $this->loginAsAdmin();
    $product = Product::factory()->configurable()->create(['sku' => 'LISTING-GRID-'.uniqid()]);
    insertListingHistory($product, [
        'attempt_id'   => 'attempt-grid-old',
        'region'       => 'MY',
        'listing_type' => 'draft',
        'status'       => 'saved',
        'completed_at' => now()->subMinutes(10),
    ]);
    insertListingHistory($product, [
        'attempt_id'   => 'attempt-grid-new',
        'region'       => 'TH',
        'listing_type' => 'review',
        'status'       => 'submitted',
        'completed_at' => now(),
    ]);

    $grid = app(ProductDataGrid::class);
    $rows = $grid->prepareQueryBuilder()->where('products.id', $product->id)->get();
    $column = $grid->getPropertyColumns()['latest_listing_result'];

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->latest_listing_region)->toBe('TH')
        ->and($rows->first()->latest_listing_type)->toBe('review')
        ->and($rows->first()->latest_listing_status)->toBe('submitted')
        ->and($column['closure']($rows->first()))->toContain('TH')
        ->toContain('审核 · 已提交');
});

it('shows an empty latest-listing state when a product has no history', function () {
    $this->loginAsAdmin();
    $product = Product::factory()->configurable()->create(['sku' => 'LISTING-GRID-EMPTY-'.uniqid()]);
    $grid = app(ProductDataGrid::class);
    $row = $grid->prepareQueryBuilder()->where('products.id', $product->id)->first();
    $column = $grid->getPropertyColumns()['latest_listing_result'];

    expect($column['closure']($row))->toContain('暂无上架');
});
