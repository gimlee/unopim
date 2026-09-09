<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

beforeEach(fn () => $this->loginAsAdmin());

it('renders 1688 collection page and sidebar menus', function () {
    $this->get(route('admin.collection.1688.index'))
        ->assertOk()
        ->assertSee('1688 商品采集')
        ->assertSee('1688 商品详情链接采集')
        ->assertSee('采集任务列表');

    $menuKeys = collect(config('menu.admin'))->pluck('key');
    expect($menuKeys)->toContain('collection')
        ->toContain('collection.1688');

    $aclKeys = collect(config('acl'))->pluck('key');
    expect($aclKeys)->toContain('collection')
        ->toContain('collection.1688')
        ->toContain('collection.1688.create')
        ->toContain('collection.1688.delete');
});

it('fetches collection tasks via ajax with pim mocking', function () {
    Http::fake([
        '*/api/jobs*' => Http::response([
            'data' => [
                [
                    'id'            => 'job-1',
                    'offer_id'      => '950299271121',
                    'title'         => '测试商品',
                    'source_url'    => 'https://detail.1688.com/offer/950299271121.html',
                    'thumbnail_url' => 'https://example.com/test.jpg',
                    'status'        => 'unopim_synced',
                    'stage'         => 'unopim_synced',
                    'created_at'    => now()->toIso8601String(),
                    'updated_at'    => now()->toIso8601String(),
                ],
            ],
            'total' => 1,
            'limit' => 20,
            'offset' => 0,
        ], 200),
    ]);

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson(route('admin.collection.1688.index'))
        ->assertOk();

    expect($response->json('records'))->toHaveCount(1);
    expect($response->json('records.0.offer_id'))->toBe('950299271121');
    expect($response->json('total'))->toBe(1);
});

it('submits a new collection task case and handles validation', function () {
    // 1. Invalid URL validation
    $this->postJson(route('admin.collection.1688.store'), [
        'url' => 'https://www.google.com',
    ])->assertStatus(422)->assertJsonPath('success', false);

    // 2. Valid URL triggers async-import
    Http::fake([
        '*/api/jobs/async-import' => Http::response([
            'success' => true,
            'data' => [
                'id'       => 'new-job-1',
                'offer_id' => '950299271121',
                'status'   => 'created',
            ],
        ], 201),
    ]);

    $created = $this->postJson(route('admin.collection.1688.store'), [
        'url' => 'https://detail.1688.com/offer/950299271121.html',
    ])->assertOk();

    expect($created->json('success'))->toBeTrue();
    expect($created->json('data.id'))->toBe('new-job-1');
});

it('retries, updates, and deletes collection tasks via controller proxy', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/api/jobs/job-1/retry') && $request->method() === 'POST') {
            return Http::response(['success' => true, 'data' => ['id' => 'job-1', 'status' => 'created']], 200);
        }
        if ($request->method() === 'PATCH' && str_contains($request->url(), '/api/jobs/job-1')) {
            return Http::response(['success' => true, 'data' => ['id' => 'job-1', 'source_url' => 'https://detail.1688.com/offer/869697188199.html']], 200);
        }
        if ($request->method() === 'DELETE' && str_contains($request->url(), '/api/jobs/job-1')) {
            return Http::response(['success' => true, 'data' => ['deleted' => true]], 200);
        }
        return Http::response(['error' => 'Not matched', 'url' => $request->url(), 'method' => $request->method()], 404);
    });

    // Retry
    $this->postJson(route('admin.collection.1688.retry', ['jobId' => 'job-1']))
        ->assertOk()
        ->assertJsonPath('success', true);

    // Update URL
    $this->putJson(route('admin.collection.1688.update', ['jobId' => 'job-1']), [
        'source_url' => 'https://detail.1688.com/offer/869697188199.html',
    ])->assertOk()->assertJsonPath('success', true);

    // Delete
    $this->deleteJson(route('admin.collection.1688.destroy', ['jobId' => 'job-1']))
        ->assertOk()
        ->assertJsonPath('success', true);
});
