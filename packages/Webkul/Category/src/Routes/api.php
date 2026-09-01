<?php

use Illuminate\Support\Facades\Route;
use Webkul\Category\Http\Controllers\Api\TaxonomyController;

Route::prefix('api/v1/rest/taxonomy')
    ->middleware(['api', 'accept.json', 'auth:api', 'throttle:rest-api', 'request.locale'])
    ->controller(TaxonomyController::class)
    ->group(function (): void {
        Route::post('sync', 'sync')->name('admin.api.taxonomy.sync');
        Route::get('status', 'status')->name('admin.api.taxonomy.status');
        Route::get('classifier', 'classifier')->name('admin.api.taxonomy.classifier');
        Route::get('resolve', 'resolve')->name('admin.api.taxonomy.resolve');
    });
