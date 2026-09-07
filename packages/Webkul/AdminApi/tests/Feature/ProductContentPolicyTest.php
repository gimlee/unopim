<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Webkul\AdminApi\Services\ProductContentPolicyService;
use Webkul\MagicAI\Enums\AiProvider;
use Webkul\MagicAI\Models\MagicAIPlatform;
use Webkul\MagicAI\Models\MagicAISystemPrompt;
use Webkul\Product\Models\Product;

uses(DatabaseTransactions::class);

it('registers authenticated content policy API routes and ships marketplace defaults', function () {
    expect(Route::has('admin.api.content_policy.words'))->toBeTrue()
        ->and(Route::has('admin.api.content_policy.optimize'))->toBeTrue();

    $words = app(ProductContentPolicyService::class)->forbiddenWords();

    expect($words)->toContain('Amazon', '亚马逊', 'Lazada', '来赞达', 'Shopee', '虾皮');
});

it('ignores structured non-text values while scanning product content', function () {
    $matched = app(ProductContentPolicyService::class)->scan([
        'description' => 'Compatible with Amazon listings',
        'price'       => ['CNY' => 19.9],
        'enabled'     => true,
    ]);

    expect($matched)->toBe(['Amazon']);
});

it('rewrites matched product copy and preserves an immutable before-after revision', function () {
    MagicAIPlatform::query()->update(['is_default' => false]);
    $platform = MagicAIPlatform::create([
        'label'      => 'Content policy test AI',
        'provider'   => AiProvider::Zhipu->value,
        'api_url'    => AiProvider::Zhipu->defaultUrl(),
        'api_key'    => 'test-key',
        'models'     => 'glm-5.3-flash',
        'is_default' => true,
        'status'     => true,
    ]);
    $product = Product::factory()->configurable()->create([
        'sku'    => 'CONTENT-POLICY-'.uniqid(),
        'values' => [
            'channel_locale_specific' => [
                'default' => [
                    'zh_CN' => [
                        'name'              => '通用商品',
                        'short_description' => '适用于 Amazon 和 LAZADA 店铺',
                        'description'       => '<p>可用于 Amazon、Lazada 和亚马逊平台。</p><p><img src="/storage/detail.jpg"></p>',
                    ],
                ],
            ],
        ],
    ]);

    $ai = Mockery::mock();
    $ai->shouldReceive('setPlatformId', 'setModel', 'setTemperature', 'setMaxTokens', 'setSystemPrompt', 'setPrompt')
        ->andReturnSelf();
    $ai->shouldReceive('ask')->once()->andReturn(json_encode([
        'name'              => '通用商品',
        'short_description' => '适用于多种线上销售场景',
        'description'       => '适用于多种线上销售场景，商品参数与原文一致。',
    ], JSON_UNESCAPED_UNICODE));
    app()->instance('magic_ai', $ai);

    $result = app(ProductContentPolicyService::class)->optimize($product, 'zh_CN', 'MY');
    $stored = data_get($product->fresh()->values, 'channel_locale_specific.default.zh_CN');
    $revision = DB::table('product_content_revisions')->where('product_id', $product->id)->first();

    expect($result['changed'])->toBeTrue()
        ->and($result['matched_terms'])->toContain('Amazon', 'Lazada', '亚马逊')
        ->and($result['provider'])->toBe($platform->label)
        ->and($stored['short_description'])->toBe('适用于多种线上销售场景')
        ->and($stored['description'])->toContain('商品参数与原文一致', '<img src="/storage/detail.jpg">')
        ->and($stored['description'])->not->toContain('Amazon', 'Lazada', '亚马逊')
        ->and($revision)->not->toBeNull()
        ->and(json_decode($revision->original_content, true)['description'])->toContain('Amazon')
        ->and(json_decode($revision->optimized_content, true)['description'])->not->toContain('Amazon');
});

it('does not call AI or create a revision when content is already compliant', function () {
    $product = Product::factory()->configurable()->create([
        'sku'    => 'CONTENT-CLEAN-'.uniqid(),
        'values' => [
            'channel_locale_specific' => [
                'default' => ['zh_CN' => [
                    'name'              => '普通商品',
                    'short_description' => '普通商品短描述',
                    'description'       => '<p>不包含平台名称。</p>',
                ]],
            ],
        ],
    ]);

    $result = app(ProductContentPolicyService::class)->optimize($product, 'zh_CN', 'MY');

    expect($result['changed'])->toBeFalse();
    $this->assertDatabaseMissing('product_content_revisions', ['product_id' => $product->id]);
});

it('falls back transparently when AI is unavailable and still removes exact managed terms', function () {
    MagicAIPlatform::query()->update(['is_default' => false]);
    MagicAIPlatform::create([
        'label'      => 'Unavailable AI',
        'provider'   => AiProvider::Zhipu->value,
        'api_url'    => AiProvider::Zhipu->defaultUrl(),
        'api_key'    => 'test-key',
        'models'     => 'glm-5.3-flash',
        'is_default' => true,
        'status'     => true,
    ]);
    $product = Product::factory()->configurable()->create([
        'sku'    => 'CONTENT-FALLBACK-'.uniqid(),
        'values' => ['channel_locale_specific' => ['default' => ['zh_CN' => [
            'name'              => '普通商品',
            'short_description' => '适合 Amazon、Lazada 销售',
            'description'       => '<p>适合 Amazon、Lazada 销售，铝合金材质。</p>',
        ]]]],
    ]);
    $ai = Mockery::mock();
    $ai->shouldReceive('setPlatformId', 'setModel', 'setTemperature', 'setMaxTokens', 'setSystemPrompt', 'setPrompt')
        ->andReturnSelf();
    $ai->shouldReceive('ask')->once()->andThrow(new RuntimeException('provider offline'));
    app()->instance('magic_ai', $ai);

    $result = app(ProductContentPolicyService::class)->optimize($product, 'zh_CN', 'MY');
    $stored = data_get($product->fresh()->values, 'channel_locale_specific.default.zh_CN');

    expect($result['changed'])->toBeTrue()
        ->and($result['method'])->toBe('ai_fallback')
        ->and($result['ai_error'])->toContain('provider offline')
        ->and($stored['description'])->toContain('线上销售渠道', '铝合金材质')
        ->and($stored['description'])->not->toContain('Amazon', 'Lazada');
    $this->assertDatabaseHas('product_content_revisions', [
        'product_id' => $product->id,
        'method'     => 'ai_fallback',
    ]);
});

it('optimizes only short description with a managed template and stores the previous version', function () {
    MagicAIPlatform::query()->update(['is_default' => false]);
    $platform = MagicAIPlatform::create([
        'label'      => 'Description test AI',
        'provider'   => AiProvider::Zhipu->value,
        'api_url'    => AiProvider::Zhipu->defaultUrl(),
        'api_key'    => 'test-key',
        'models'     => 'glm-5.3-flash',
        'is_default' => true,
        'status'     => true,
    ]);
    $template = MagicAISystemPrompt::query()
        ->where('purpose', 'product_description')
        ->where('is_enabled', true)
        ->firstOrFail();
    $product = Product::factory()->configurable()->create([
        'sku'    => 'DESCRIPTION-AI-'.uniqid(),
        'values' => ['channel_locale_specific' => ['default' => ['zh_CN' => [
            'name'              => '铝合金支架',
            'short_description' => '产自深圳，用于 Amazon 销售。',
            'description'       => '<p>铝合金结构，可折叠调节角度。</p>',
        ]]]],
    ]);
    $ai = Mockery::mock();
    $ai->shouldReceive('setPlatformId', 'setModel', 'setTemperature', 'setMaxTokens', 'setSystemPrompt', 'setPrompt')
        ->andReturnSelf();
    $ai->shouldReceive('ask')->once()->andReturn(json_encode([
        'short_description' => '采用铝合金可折叠结构，可调节角度并稳固支撑兼容设备。',
    ], JSON_UNESCAPED_UNICODE));
    app()->instance('magic_ai', $ai);

    $result = app(ProductContentPolicyService::class)->optimizeShortDescription(
        $product,
        'zh_CN',
        'default',
        $template->id,
    );
    $stored = data_get($product->fresh()->values, 'channel_locale_specific.default.zh_CN');
    $revision = DB::table('product_content_revisions')->where('id', $result['revision_id'])->first();

    expect($result['method'])->toBe('ai_description')
        ->and($result['provider'])->toBe($platform->label)
        ->and($stored['short_description'])->toBe('采用铝合金可折叠结构，可调节角度并稳固支撑兼容设备。')
        ->and($stored['description'])->toBe('<p>铝合金结构，可折叠调节角度。</p>')
        ->and(json_decode($revision->original_content, true)['short_description'])->toContain('产自深圳')
        ->and($revision->template_id)->toBe($template->id)
        ->and($revision->prompt_snapshot)->toContain('只描述商品本身');
});
