<?php

use Laravel\Ai\Gateway\TextGenerationOptions;
use Webkul\MagicAI\Agents\MagicContentAgent;

it('resolves temperature and max tokens into the native generation options', function () {
    $agent = new MagicContentAgent(
        systemPrompt: 'You are a copywriter.',
        temperature: 0.3,
        maxTokens: 2048,
    );

    $options = TextGenerationOptions::forAgent($agent);

    expect($options->temperature)->toBe(0.3);
    expect($options->maxTokens)->toBe(2048);
});

it('omits temperature when null so reasoning models accept the request', function () {
    $agent = new MagicContentAgent(
        systemPrompt: 'You are a copywriter.',
        temperature: null,
        maxTokens: 16000,
    );

    $options = TextGenerationOptions::forAgent($agent);

    expect($options->temperature)->toBeNull();
    expect($options->maxTokens)->toBe(16000);
});

it('passes GLM thinking options into the provider request body options', function () {
    $agent = new MagicContentAgent(
        providerOptions: [
            'thinking'         => ['type' => 'enabled'],
            'reasoning_effort' => 'low',
        ],
    );

    $options = TextGenerationOptions::forAgent($agent);

    expect($options->providerOptions('openai-compatible'))->toBe([
        'thinking'         => ['type' => 'enabled'],
        'reasoning_effort' => 'low',
    ]);
});
