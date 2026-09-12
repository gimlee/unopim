<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Webkul\Admin\Jobs\TranslateProductImages;
use Webkul\Admin\Services\ProductImageTranslationService;
use Webkul\Product\Models\Product;

uses(DatabaseTransactions::class);

it('accepts image translation without waiting and prevents duplicate active jobs', function () {
    $this->loginAsAdmin();
    Queue::fake();
    $product = Product::factory()->configurable()->create();
    $payload = [
        'images'      => [['id' => 'main', 'type' => 'main', 'original_url' => 'https://example.test/source.jpg']],
        'target_lang' => 'th',
        'source_lang' => 'zh',
        'region'      => 'TH',
        'platform'    => 'aeAi',
    ];

    $first = $this->postJson(route('admin.catalog.products.image_translations.translate', $product->id), $payload);
    $second = $this->postJson(route('admin.catalog.products.image_translations.translate', $product->id), $payload);

    $first->assertStatus(202)->assertJsonPath('status', 'queued');
    $second->assertStatus(202)->assertJsonPath('job_id', $first->json('job_id'));
    Queue::assertPushedOn('image-translations', TranslateProductImages::class);
    expect(DB::table('product_image_translation_jobs')->where('product_id', $product->id)->count())->toBe(1);
});

it('persists running and completed translation results for status polling', function () {
    $this->loginAsAdmin();
    $product = Product::factory()->configurable()->create();
    $jobId = (string) Str::uuid();
    $payload = [
        'images'      => [['type' => 'main', 'original_url' => 'https://example.test/source.jpg']],
        'target_lang' => 'th',
        'source_lang' => 'zh',
        'region'      => 'TH',
        'platform'    => 'aeAi',
    ];

    DB::table('product_image_translation_jobs')->insert([
        'id'         => $jobId,
        'product_id' => $product->id,
        'status'     => 'queued',
        'payload'    => json_encode($payload),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Http::fake([
        '*/api/image-translation/translate' => Http::response([
            'results' => [['translated_url' => 'https://example.test/translated.jpg']],
        ]),
    ]);

    (new TranslateProductImages($jobId))->handle(app(ProductImageTranslationService::class));

    $this->getJson(route('admin.catalog.products.image_translations.status', [$product->id, $jobId]))
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.results.0.translated_url', 'https://example.test/translated.jpg');
});

it('resumes an active translation and exposes a terminal worker failure', function () {
    $this->loginAsAdmin();
    $product = Product::factory()->configurable()->create();
    $jobId = (string) Str::uuid();

    DB::table('product_image_translation_jobs')->insert([
        'id'         => $jobId,
        'product_id' => $product->id,
        'status'     => 'running',
        'payload'    => json_encode(['images' => []]),
        'started_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->getJson(route('admin.catalog.products.image_translations.index', $product->id))
        ->assertOk()
        ->assertJsonPath('active_job.id', $jobId)
        ->assertJsonPath('active_job.status', 'running');

    (new TranslateProductImages($jobId))->failed(new RuntimeException('translation worker failed'));

    $this->getJson(route('admin.catalog.products.image_translations.status', [$product->id, $jobId]))
        ->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.error', 'translation worker failed');
});

it('renders resumable image translation polling in the existing product editor', function () {
    $this->loginAsAdmin();
    $product = Product::factory()->configurable()->create();

    $this->get(route('admin.catalog.products.edit', $product->id))
        ->assertOk()
        ->assertSee('status-url-template', false)
        ->assertSee('pollTranslationStatus', false);
});
