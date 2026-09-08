<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Webkul\AdminApi\Tests\Traits\ApiHelperTrait;
use Webkul\Product\Models\Product;

uses(ApiHelperTrait::class, DatabaseTransactions::class);

it('stores listing exceptions idempotently against the root product', function () {
    $headers = $this->getAuthenticationHeaders();
    $product = Product::factory()->configurable()->create(['sku' => 'LISTING-EXCEPTION-'.uniqid()]);
    $payload = [
        'platform'   => 'tiktok',
        'region'     => 'MY',
        'attempt_id' => 'attempt-123',
        'exceptions' => [[
            'event_key'       => 'captcha-detected-once',
            'exception_type'  => 'captcha',
            'stage'           => 'initial login',
            'severity'        => 'warning',
            'message'         => '检测到验证码，等待用户完成。',
            'requires_manual' => true,
            'blocking'        => false,
            'details'         => ['action' => 'resolved_by_user'],
            'occurred_at'     => '2026-09-08T00:00:00+00:00',
        ]],
    ];
    $url = '/api/v1/rest/listing-exceptions/products/'.$product->sku;

    $this->postJson($url, $payload, $headers)
        ->assertOk()
        ->assertJsonPath('data.count', 1)
        ->assertJsonPath('data.product_id', $product->id);
    $this->postJson($url, $payload, $headers)->assertOk();

    expect(DB::table('product_listing_exceptions')->where('product_id', $product->id)->count())->toBe(1);
    $this->assertDatabaseHas('product_listing_exceptions', [
        'product_id'       => $product->id,
        'exception_type'   => 'captcha',
        'requires_manual'  => true,
        'blocking'         => false,
    ]);
});
