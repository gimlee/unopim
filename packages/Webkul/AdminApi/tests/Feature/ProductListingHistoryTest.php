<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Webkul\AdminApi\Tests\Traits\ApiHelperTrait;
use Webkul\Product\Models\Product;

uses(ApiHelperTrait::class, DatabaseTransactions::class);

it('upserts listing history idempotently against the root product', function () {
    $headers = $this->getAuthenticationHeaders();
    $parent = Product::factory()->configurable()->create(['sku' => 'LISTING-HISTORY-'.uniqid()]);
    $variant = Product::factory()->simple()->create([
        'sku'       => $parent->sku.'-BLUE',
        'parent_id' => $parent->id,
    ]);
    $payload = [
        'attempt_id'   => 'attempt-history-123',
        'sku'          => $variant->sku,
        'platform'     => 'tiktok',
        'region'       => 'MY',
        'listing_type' => 'draft',
        'status'       => 'filling',
        'published'    => false,
        'started_at'   => '2026-09-09T00:00:00+00:00',
    ];
    $url = '/api/v1/rest/listing-history/products/'.$variant->sku;

    $this->postJson($url, $payload, $headers)
        ->assertOk()
        ->assertJsonPath('data.product_id', $parent->id)
        ->assertJsonPath('data.product_sku', $parent->sku);
    $this->postJson($url, array_merge($payload, [
        'status'       => 'saved',
        'completed_at' => '2026-09-09T00:05:00+00:00',
        'draft_url'    => 'https://seller.tiktok.com/draft/123',
    ]), $headers)->assertOk();

    $records = DB::table('product_listing_histories')
        ->where('product_id', $parent->id)
        ->where('attempt_id', 'attempt-history-123')
        ->get();

    expect($records)->toHaveCount(1)
        ->and($records->first()->sku)->toBe($parent->sku)
        ->and($records->first()->status)->toBe('saved')
        ->and($records->first()->listing_type)->toBe('draft');
});

it('rejects published listing history because this workflow only records drafts and reviews', function () {
    $headers = $this->getAuthenticationHeaders();
    $product = Product::factory()->configurable()->create(['sku' => 'LISTING-NO-PUBLISH-'.uniqid()]);

    $this->postJson('/api/v1/rest/listing-history/products/'.$product->sku, [
        'attempt_id'   => 'attempt-published',
        'platform'     => 'tiktok',
        'region'       => 'TH',
        'listing_type' => 'review',
        'status'       => 'published',
        'published'    => true,
    ], $headers)->assertUnprocessable();
});
