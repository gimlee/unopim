<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\MagicAI\Models\MagicAISystemPrompt;

uses(DatabaseTransactions::class);

beforeEach(fn () => $this->loginAsAdmin());

it('shows managed AI classification and product description prompt purposes', function () {
    $this->get(route('admin.magic_ai.system_prompt.index'))
        ->assertOk()
        ->assertSee('AI 商品分类')
        ->assertSee('商品描述优化 / 描述模板');

    expect(MagicAISystemPrompt::where('purpose', 'category_classification')->where('is_enabled', true)->exists())->toBeTrue()
        ->and(MagicAISystemPrompt::where('purpose', 'product_description')->where('is_enabled', true)->exists())->toBeTrue();
});

it('keeps one enabled system prompt per purpose without disabling other purposes', function () {
    $general = MagicAISystemPrompt::where('purpose', 'general')->firstOrFail();
    $general->update(['is_enabled' => true]);

    $this->postJson(route('admin.magic_ai.system_prompt.store'), [
        'title'       => '第二个描述模板',
        'purpose'     => 'product_description',
        'tone'        => '只描述商品本身，语言通顺。',
        'is_enabled'  => true,
        'max_tokens'  => 800,
        'temperature' => 0.2,
    ])->assertOk();

    expect(MagicAISystemPrompt::where('purpose', 'product_description')->where('is_enabled', true)->count())->toBe(1)
        ->and($general->fresh()->is_enabled)->toBeTrue();
});
