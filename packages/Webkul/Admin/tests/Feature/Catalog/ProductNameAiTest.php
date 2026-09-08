<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Webkul\AdminApi\Services\ProductContentPolicyService;
use Webkul\MagicAI\Enums\AiProvider;
use Webkul\MagicAI\Models\MagicAIPlatform;
use Webkul\MagicAI\Models\MagicAISystemPrompt;
use Webkul\Product\Models\Product;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->loginAsAdmin();
});

it('can fetch product name optimization templates', function () {
    $response = $this->getJson(route('admin.catalog.products.name_ai.templates'));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toBeArray();
    expect(collect($data)->pluck('title'))->toContain('商品名称优化（标准模板）');
});

it('can update a product name optimization template', function () {
    $template = MagicAISystemPrompt::query()->where('purpose', 'product_name')->firstOrFail();

    $response = $this->putJson(route('admin.catalog.products.name_ai.templates.update', $template->id), [
        'title'   => '修改后的商品名称模板',
        'content' => '测试新提示词内容。',
    ]);

    $response->assertOk();
    expect($template->fresh()->title)->toBe('修改后的商品名称模板');
    expect($template->fresh()->tone)->toBe('测试新提示词内容。');
});

it('optimizes product name, intercepts factory/wholesale terms and persists revision', function () {
    MagicAIPlatform::query()->update(['is_default' => false]);
    MagicAIPlatform::create([
        'label'      => 'Name AI Test Platform',
        'provider'   => AiProvider::Zhipu->value,
        'api_url'    => AiProvider::Zhipu->defaultUrl(),
        'api_key'    => 'test-key',
        'models'     => 'glm-5.3-flash',
        'is_default' => true,
        'status'     => true,
    ]);

    $template = MagicAISystemPrompt::query()->where('purpose', 'product_name')->firstOrFail();
    $product = Product::factory()->configurable()->create([
        'sku'    => 'NAME-AI-'.uniqid(),
        'values' => [
            'channel_locale_specific' => [
                'default' => [
                    'zh_CN' => [
                        'name'              => '1688源头工厂直销折叠手机支架',
                        'short_description' => '金属材质，坚固耐用。',
                        'description'       => '<p>多角度可调，便携折叠。</p>',
                    ],
                    'en_US' => [
                        'name'              => '1688源头工厂直销折叠手机支架',
                        'short_description' => '金属材质，坚固耐用。',
                        'description'       => '<p>多角度可调，便携折叠。</p>',
                    ],
                ],
            ],
        ],
    ]);

    $ai = Mockery::mock();
    $ai->shouldReceive('setPlatformId', 'setModel', 'setTemperature', 'setMaxTokens', 'setSystemPrompt', 'setPrompt')
        ->andReturnSelf();
    // First attempt returns violation with "厂家直销"
    $ai->shouldReceive('ask')->once()->andReturn('{"name":"厂家直销多功能可折叠铝合金桌面手机平板支架"}');
    // Second attempt removes factory terms and expands features
    $ai->shouldReceive('ask')->once()->andReturn('{"name":"多功能可折叠铝合金桌面手机平板支架多角度升降便携支撑"}');
    app()->instance('magic_ai', $ai);

    $response = $this->postJson(route('admin.catalog.products.name_ai.optimize', $product->id), [
        'channel'     => 'default',
        'locale'      => 'zh_CN',
        'template_id' => $template->id,
    ]);

    $response->assertOk();
    $data = $response->json();
    expect($data['data']['name'])->toBe('多功能可折叠铝合金桌面手机平板支架多角度升降便携支撑');
    expect($data['data']['name'])->not->toContain('厂家', '直销', '源头工厂');

    $freshValues = $product->fresh()->values;
    $storedZh = data_get($freshValues, 'channel_locale_specific.default.zh_CN.name');
    expect($storedZh)->toBe('多功能可折叠铝合金桌面手机平板支架多角度升降便携支撑');

    $storedEn = data_get($freshValues, 'channel_locale_specific.default.en_US.name');
    expect($storedEn)->toBe('多功能可折叠铝合金桌面手机平板支架多角度升降便携支撑');

    $backupOriginal = data_get($freshValues, 'common.original_name');
    expect($backupOriginal)->toBe('1688源头工厂直销折叠手机支架');

    $revision = DB::table('product_content_revisions')
        ->where('product_id', $product->id)
        ->where('method', 'ai_name')
        ->first();
    expect($revision)->not->toBeNull();
});
