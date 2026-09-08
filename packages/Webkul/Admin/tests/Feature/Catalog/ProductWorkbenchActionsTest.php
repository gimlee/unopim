<?php

use Illuminate\Support\Facades\Http;
use Webkul\Admin\DataGrids\Catalog\ProductDataGrid;
use Webkul\Category\Services\ProductCategoryAiClassifier;
use Webkul\Category\Services\ProductCategoryClassificationManager;
use Webkul\AdminApi\Services\ProductContentPolicyService;
use Webkul\Product\Models\Product;

it('redirects to zh_CN when locale is not provided on edit page', function () {
    $this->loginAsAdmin();

    $product = Product::factory()->create(['type' => 'simple']);

    $response = $this->get(route('admin.catalog.products.edit', $product->id));

    $response->assertRedirect();
    $targetUrl = $response->headers->get('Location');
    expect($targetUrl)->toContain('locale=zh_CN');
});

it('retains specified locale when provided in edit page request', function () {
    $this->loginAsAdmin();

    $product = Product::factory()->create(['type' => 'simple']);

    $response = $this->get(route('admin.catalog.products.edit', [
        'id'      => $product->id,
        'channel' => 'default',
        'locale'  => 'en_US',
    ]));

    $response->assertOk();
    expect($response->getContent())->toContain('v-product-workbench-actions');
});

it('registers ai_optimize action in product datagrid', function () {
    $this->loginAsAdmin();

    $datagrid = app(ProductDataGrid::class);
    $datagrid->prepareActions();

    $actions = collect($datagrid->getActions());
    $aiAction = $actions->firstWhere('index', 'ai_optimize');

    expect($aiAction)->not->toBeNull();
    expect($aiAction->title)->toBe('AI 优化');
    expect($aiAction->method)->toBe('POST');
    expect($aiAction->icon)->toBe('icon-magic');
});

it('calls listing-draft endpoint and handles pim response', function () {
    $this->loginAsAdmin();

    $product = Product::factory()->create([
        'sku'  => 'TEST-SKU-WORKBENCH-1',
        'type' => 'simple',
    ]);

    Http::fake([
        '*/api/products/TEST-SKU-WORKBENCH-1/listing/run' => Http::response([
            'success' => true,
            'data'    => [
                'command' => 'opencli tk-seller draft TEST-SKU-WORKBENCH-1 ...',
                'output'  => 'done',
            ],
        ], 200),
    ]);

    $response = $this->postJson(route('admin.catalog.products.listing_draft', $product->id), [
        'region' => 'MY',
    ]);

    $response->assertOk();
    $data = $response->json();
    expect($data['success'])->toBeTrue();
    expect($data['message'])->toContain('TikTok Shop 草稿已生成成功');
});

it('runs aiOptimize endpoint and returns results', function () {
    $this->loginAsAdmin();

    $product = Product::factory()->create([
        'sku'  => 'TEST-SKU-AI-1',
        'type' => 'simple',
    ]);

    $this->mock(ProductCategoryAiClassifier::class, function ($mock) {
        $mock->shouldReceive('classify')->andReturn(['category_id' => 1]);
    });

    $this->mock(ProductCategoryClassificationManager::class, function ($mock) {
        $mock->shouldReceive('remember')->andReturn((object) ['id' => 1]);
        $mock->shouldReceive('apply')->andReturn((object) ['id' => 1]);
        $mock->shouldReceive('present')->andReturn(['category' => 'Home & Kitchen']);
    });

    $this->mock(ProductContentPolicyService::class, function ($mock) {
        $mock->shouldReceive('nameTemplates')->andReturn([
            ['id' => 1, 'title' => 'Default Name', 'is_default' => true],
        ]);
        $mock->shouldReceive('optimizeProductName')->andReturn([
            'name' => '优化的商品名称',
        ]);
        $mock->shouldReceive('descriptionTemplates')->andReturn([
            ['id' => 1, 'title' => 'Default', 'is_default' => true],
        ]);
        $mock->shouldReceive('optimizeShortDescription')->andReturn([
            'short_description' => '<p>优化的短描述内容。</p>',
        ]);
    });

    \Webkul\MagicAI\Models\MagicAIPlatform::create([
        'label'      => 'Test AI Platform',
        'provider'   => 'openai',
        'status'     => true,
        'is_default' => true,
        'models'     => json_encode(['gpt-4o-flash']),
    ]);

    $response = $this->postJson(route('admin.catalog.products.ai_optimize', $product->id), [
        'locale'  => 'zh_CN',
        'channel' => 'default',
    ]);

    $response->assertOk();
    $data = $response->json();
    expect($data['success'])->toBeTrue();
    expect($data['message'])->toContain('AI 优化完成');
});

it('calls listing-draft endpoint with submit action and forwards submit_for_review', function () {
    $this->loginAsAdmin();

    $product = Product::factory()->create([
        'sku'  => 'TEST-SKU-SUBMIT-1',
        'type' => 'simple',
    ]);

    $pimPayload = null;
    Http::fake([
        '*/api/products/*/listing/run' => function ($request) use (&$pimPayload) {
            $pimPayload = $request->data();
            return Http::response([
                'success' => true,
                'data'    => [
                    'command'   => 'opencli tk-seller submit TEST-SKU-SUBMIT-1',
                    'submitted' => true,
                ],
            ], 200);
        },
    ]);

    $response = $this->postJson(route('admin.catalog.products.listing_draft', $product->id), [
        'region' => 'MY',
        'action' => 'submit',
    ]);

    $response->assertOk();
    $data = $response->json();
    expect($data['success'])->toBeTrue();
    expect($data['message'])->toContain('上架审核提交成功');
    expect($pimPayload['submit_for_review'])->toBeTrue();
});

it('skips AI optimization when name, description and category are already optimized', function () {
    $this->loginAsAdmin();

    $product = Product::factory()->create([
        'sku'    => 'TEST-SKU-DEDUP-1',
        'type'   => 'simple',
        'values' => [
            'common' => [
                'categories'                     => [1],
                'category_classification_method' => 'ai',
            ],
        ],
    ]);

    \Illuminate\Support\Facades\DB::table('product_content_revisions')->insert([
        [
            'product_id'        => $product->id,
            'platform'          => 'tiktok',
            'region'            => 'MY',
            'locale'            => 'zh_CN',
            'matched_terms'     => json_encode([]),
            'original_content'  => json_encode(['name' => '原名称']),
            'optimized_content' => json_encode(['name' => 'AI优化后名称']),
            'method'            => 'ai_name',
            'created_at'        => now(),
            'updated_at'        => now(),
        ],
        [
            'product_id'        => $product->id,
            'platform'          => 'tiktok',
            'region'            => 'MY',
            'locale'            => 'zh_CN',
            'matched_terms'     => json_encode([]),
            'original_content'  => json_encode(['short_description' => '原描述']),
            'optimized_content' => json_encode(['short_description' => 'AI优化后描述']),
            'method'            => 'ai_description',
            'created_at'        => now(),
            'updated_at'        => now(),
        ],
    ]);

    $response = $this->postJson(route('admin.catalog.products.ai_optimize', $product->id), [
        'locale'  => 'zh_CN',
        'channel' => 'default',
    ]);

    $response->assertOk();
    $data = $response->json();
    expect($data['success'])->toBeTrue();
    expect($data['message'])->toContain('均已优化，无需重复执行');
});

