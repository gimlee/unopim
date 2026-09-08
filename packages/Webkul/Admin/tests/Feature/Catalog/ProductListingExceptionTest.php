<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Webkul\Product\Models\Product;

uses(DatabaseTransactions::class);

it('shows listing exceptions on the product page and lets an admin resolve them', function () {
    $this->loginAsAdmin();
    $product = Product::factory()->configurable()->create(['sku' => 'LISTING-REVIEW-'.uniqid()]);
    $exceptionId = DB::table('product_listing_exceptions')->insertGetId([
        'product_id'       => $product->id,
        'sku'              => $product->sku,
        'platform'         => 'tiktok',
        'region'           => 'MY',
        'attempt_id'       => 'attempt-review',
        'event_key'        => hash('sha256', 'draft-save'),
        'exception_type'   => 'draft_save_failed',
        'stage'            => 'save draft',
        'severity'         => 'error',
        'message'          => '草稿无法保存。',
        'requires_manual'  => true,
        'blocking'         => true,
        'details'          => json_encode(['validation' => 'missing field']),
        'occurred_at'      => now(),
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    $this->get(route('admin.catalog.products.edit', $product->id))
        ->assertOk()
        ->assertSee('上架异常记录')
        ->assertViewHas('listingExceptions', function ($exceptions): bool {
            return $exceptions->contains(fn ($exception): bool => $exception->message === '草稿无法保存。');
        });
    $this->patchJson(route('admin.catalog.products.listing_exceptions.update', [$product->id, $exceptionId]), [
        'resolved' => true,
    ])->assertOk();

    expect(DB::table('product_listing_exceptions')->where('id', $exceptionId)->value('resolved_at'))->not->toBeNull();
});
