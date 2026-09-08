<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Webkul\AdminApi\Tests\Traits\ApiHelperTrait;
use Webkul\MagicAI\Models\MagicAISystemPrompt;

uses(ApiHelperTrait::class, DatabaseTransactions::class);

it('syncs magic-ai system prompts idempotently and updates modified values', function () {
    $headers = $this->getAuthenticationHeaders();
    $url = '/api/v1/rest/magic-ai/system-prompts/sync';

    $payload = [
        'prompts' => [
            [
                'title'       => 'Test Prompt 1',
                'purpose'     => 'test_purpose',
                'tone'        => 'Initial prompt text',
                'max_tokens'  => 500,
                'temperature' => 0.4,
                'is_enabled'  => true,
            ],
            [
                'title'       => 'Test Prompt 2',
                'purpose'     => 'test_purpose_2',
                'tone'        => 'Another text',
                'max_tokens'  => 1000,
                'temperature' => 0.6,
                'is_enabled'  => true,
            ],
        ],
    ];

    // First call: creates both prompts
    $response = $this->postJson($url, $payload, $headers)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total', 2);

    expect($response->json('data.created'))->toContain('test_purpose:Test Prompt 1')
        ->and($response->json('data.created'))->toContain('test_purpose_2:Test Prompt 2');

    // Second call with same payload: unchanged
    $response2 = $this->postJson($url, $payload, $headers)
        ->assertOk()
        ->assertJsonPath('data.unchanged', ['test_purpose:Test Prompt 1', 'test_purpose_2:Test Prompt 2']);

    // Third call with modified tone on Prompt 1: updated
    $payload['prompts'][0]['tone'] = 'Updated prompt text';
    $response3 = $this->postJson($url, $payload, $headers)
        ->assertOk()
        ->assertJsonPath('data.updated', ['test_purpose:Test Prompt 1'])
        ->assertJsonPath('data.unchanged', ['test_purpose_2:Test Prompt 2']);

    $saved = MagicAISystemPrompt::where('purpose', 'test_purpose')->where('title', 'Test Prompt 1')->first();
    expect($saved)->not->toBeNull()
        ->and($saved->tone)->toBe('Updated prompt text');
});

it('syncs forbidden words idempotently and normalizes terms', function () {
    $headers = $this->getAuthenticationHeaders();
    $url = '/api/v1/rest/content-policy/forbidden-words/sync';

    $uniqueTerm1 = 'SyncTestWord1_'.uniqid();
    $uniqueTerm2 = 'SyncTestWord2_'.uniqid();

    $payload = [
        'words' => [$uniqueTerm1, $uniqueTerm2],
    ];

    // First sync adds the new words
    $res1 = $this->postJson($url, $payload, $headers)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.added', 2);

    // Second sync with identical words should report unchanged
    $res2 = $this->postJson($url, $payload, $headers)
        ->assertOk()
        ->assertJsonPath('data.added', 0)
        ->assertJsonPath('data.unchanged', 2);

    // Verify case normalization
    $this->assertDatabaseHas('content_policy_forbidden_words', [
        'term'            => $uniqueTerm1,
        'normalized_term' => mb_strtolower($uniqueTerm1),
    ]);
});
