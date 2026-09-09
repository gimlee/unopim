<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Collection\Collection1688Controller;

/**
 * Collection routes.
 */
Route::group(['middleware' => ['admin'], 'prefix' => config('app.admin_url')], function () {
    Route::prefix('collection')->group(function () {
        Route::controller(Collection1688Controller::class)->prefix('1688')->group(function () {
            Route::get('', 'index')->name('admin.collection.1688.index');
            Route::post('', 'store')->name('admin.collection.1688.store');
            Route::post('{jobId}/retry', 'retry')->name('admin.collection.1688.retry');
            Route::put('{jobId}', 'update')->name('admin.collection.1688.update');
            Route::delete('{jobId}', 'destroy')->name('admin.collection.1688.destroy');
        });
    });
});
