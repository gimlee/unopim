<?php

use Illuminate\Support\Facades\Route;
use Webkul\Category\Http\Controllers\Admin\TaxonomyController;

Route::middleware(['web', 'admin'])
    ->prefix('admin/catalog/taxonomy')
    ->controller(TaxonomyController::class)
    ->group(function (): void {
        Route::get('', 'index')->name('admin.catalog.taxonomy.index');
        Route::post('mappings', 'storeMapping')->name('admin.catalog.taxonomy.mappings.store');
        Route::delete('mappings/{id}', 'destroyMapping')->whereNumber('id')->name('admin.catalog.taxonomy.mappings.delete');
        Route::put('assignments/{id}', 'reviewAssignment')->whereNumber('id')->name('admin.catalog.taxonomy.assignments.review');
    });
