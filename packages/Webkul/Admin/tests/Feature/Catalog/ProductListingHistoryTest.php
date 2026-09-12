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

function listingGridRecords(array $productIds): array
{
    request()->replace([
        'managedColumns' => ['product_id', 'sku', 'latest_listing_result'],
        'productIds'     => $productIds,
        'sort'           => ['column' => 'sku', 'order' => 'asc'],
    ]);

    $grid = app(ProductDataGrid::class);
    $grid->prepare();

    return json_decode(json_encode($grid->formatData()['records']), true);
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

    $rows = listingGridRecords([$product->id]);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['latest_listing_result'])->toContain('TH')
        ->toContain('审核 · 已提交');
});

it('shows an empty latest-listing state when a product has no history', function () {
    $this->loginAsAdmin();
    $product = Product::factory()->configurable()->create(['sku' => 'LISTING-GRID-EMPTY-'.uniqid()]);
    $rows = listingGridRecords([$product->id]);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['latest_listing_result'])->toContain('暂无上架');
});

it('loads latest listing results once for the current page without duplicating rows', function () {
    $this->loginAsAdmin();

    $products = collect(range(1, 4))->map(fn ($index) => Product::factory()->configurable()->create([
        'sku' => 'LISTING-BATCH-'.$index.'-'.uniqid(),
    ]));

    foreach ($products as $product) {
        insertListingHistory($product, ['completed_at' => now()->subMinute()]);
        insertListingHistory($product, ['region' => 'SG', 'status' => 'submitted']);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $rows = listingGridRecords($products->pluck('id')->reverse()->all());
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $historyQueries = $queries->filter(fn ($entry) => str_contains($entry['query'], 'product_listing_histories'));

    expect($rows)->toHaveCount(4)
        ->and(collect($rows)->pluck('product_id')->sort()->values()->all())->toBe($products->pluck('id')->sort()->values()->all())
        ->and(collect($rows)->every(fn ($row) => str_contains($row['latest_listing_result'], 'SG')))->toBeTrue()
        ->and($historyQueries)->toHaveCount(1);
});
