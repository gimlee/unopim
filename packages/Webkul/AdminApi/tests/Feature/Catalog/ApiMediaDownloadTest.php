<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->headers = $this->getAuthenticationHeaders();
    Storage::fake();
});

it('downloads product media through the authenticated REST API', function () {
    Storage::put('product/29/image/photo.jpg', 'image-bytes');

    $this->withHeaders($this->headers)
        ->get(route('admin.api.media-files.download', ['path' => 'product/29/image/photo.jpg']))
        ->assertOk()
        ->assertHeader('Content-Disposition');
});

it('requires API authentication and rejects paths outside product media', function () {
    Storage::put('category/1/image/photo.jpg', 'image-bytes');

    $this->getJson(route('admin.api.media-files.download', ['path' => 'product/29/image/photo.jpg']))
        ->assertUnauthorized();

    $this->withHeaders($this->headers)
        ->getJson(route('admin.api.media-files.download', ['path' => 'category/1/image/photo.jpg']))
        ->assertNotFound();

    $this->withHeaders($this->headers)
        ->getJson(route('admin.api.media-files.download', ['path' => 'product/../../.env']))
        ->assertNotFound();
});
