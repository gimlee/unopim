<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

beforeEach(fn () => $this->loginAsAdmin());

it('renders forbidden words as a catalog CRUD page', function () {
    $this->get(route('admin.catalog.forbidden_words.index'))
        ->assertOk()
        ->assertSee('违禁词')
        ->assertSee('新增违禁词');

    expect(collect(config('menu.admin'))->pluck('key'))->toContain('catalog.forbidden_words');
});

it('returns datagrid records via ajax', function () {
    $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get(route('admin.catalog.forbidden_words.index'))
        ->assertOk()
        ->assertJsonStructure([
            'records',
            'columns',
            'meta',
        ]);

    $this->getJson(route('admin.catalog.forbidden_words.index'))
        ->assertOk()
        ->assertJsonStructure([
            'records',
            'columns',
            'meta',
        ]);
});

it('creates updates and deletes a forbidden word case insensitively', function () {
    $created = $this->postJson(route('admin.catalog.forbidden_words.store'), [
        'term'   => 'ExampleMall',
        'status' => true,
        'notes'  => '测试平台',
    ])->assertOk();
    $id = $created->json('data.id');

    $this->assertDatabaseHas('content_policy_forbidden_words', [
        'id'              => $id,
        'term'            => 'ExampleMall',
        'normalized_term' => 'examplemall',
    ]);

    $this->postJson(route('admin.catalog.forbidden_words.store'), [
        'term'   => 'examplemall',
        'status' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('term');

    $this->putJson(route('admin.catalog.forbidden_words.update', $id), [
        'term'   => 'Example Mall',
        'status' => false,
        'notes'  => '已停用',
    ])->assertOk();
    $this->getJson(route('admin.catalog.forbidden_words.show', $id))
        ->assertOk()
        ->assertJsonPath('data.status', false);

    $this->deleteJson(route('admin.catalog.forbidden_words.destroy', $id))->assertOk();
    expect(DB::table('content_policy_forbidden_words')->where('id', $id)->exists())->toBeFalse();
});
