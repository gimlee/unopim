<?php

use Webkul\MagicAI\Models\MagicAIPlatform;

it('should accept a comma-separated models string when creating a platform (Issue #685)', function () {
    $this->loginAsAdmin();

    $response = $this->postJson(route('admin.magic_ai.platform.store'), [
        'label'    => 'Models String '.uniqid(),
        'provider' => 'openai',
        'api_key'  => 'sk-test',
        'models'   => 'gpt-4,gpt-4o-mini',
        'status'   => 1,
    ]);

    $response->assertStatus(200);

    expect(MagicAIPlatform::latest('id')->first()->models)->toContain('gpt-4');
});

it('should always set the models field in the platform form FormData submit path', function () {
    $view = file_get_contents(__DIR__.'/../../../src/Resources/views/configuration/magic-ai/platform/index.blade.php');

    expect($view)
        ->toContain("saveData.set('models', this.selectedModels.join(','));")
        ->toContain('this.form.label = this.providerLabels[this.form.provider] || this.form.provider;')
        ->toContain('this.form.provider !== this.lastProvider')
        ->toContain("route('admin.magic_ai.platform.test_model')")
        ->toContain('reasoning_effort: this.form.reasoning_effort')
        ->toContain('testModelAvailability()');
});

it('uses the provider name when a platform label is omitted', function () {
    $this->loginAsAdmin();

    $this->postJson(route('admin.magic_ai.platform.store'), [
        'label'    => '',
        'provider' => 'zhipu_code_plan',
        'api_url'  => 'https://open.bigmodel.cn/api/coding/paas/v4',
        'api_key'  => 'test-key',
        'models'   => 'glm-5.3-flash',
        'status'   => 1,
    ])->assertOk();

    expect(MagicAIPlatform::latest('id')->first()->label)
        ->toBe('智谱 Code Plan（仅编码工具）');
});
