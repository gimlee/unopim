<?php

use Illuminate\Support\Facades\Route;
use Webkul\Category\Http\Controllers\Admin\TaxonomyController;
use Webkul\Category\Http\Controllers\Admin\TaxonomySelectorController;
use Webkul\Category\Http\Controllers\Admin\ProductTaxonomyController;

Route::middleware(['web', 'admin'])
    ->get('admin/catalog/taxonomy-selector/options', [TaxonomySelectorController::class, 'options'])
    ->name('admin.catalog.taxonomy.selector.options');

Route::middleware(['web', 'admin'])
    ->prefix('admin/catalog/products/{productId}/taxonomy')
    ->controller(ProductTaxonomyController::class)
    ->group(function (): void {
        Route::put('', 'update')->whereNumber('productId')->name('admin.catalog.products.taxonomy.update');
        Route::post('ai-suggest', 'aiSuggest')->whereNumber('productId')->name('admin.catalog.products.taxonomy.ai-suggest');
    });

Route::middleware(['web', 'admin'])
    ->prefix('admin/catalog/taxonomy')
    ->controller(TaxonomyController::class)
    ->group(function (): void {
        Route::get('', 'index')->name('admin.catalog.taxonomy.index');
        Route::get('mappings', 'mappings')->name('admin.catalog.taxonomy.mappings.index');
        Route::get('mappings/source-1688', 'sourceMappings')->name('admin.catalog.taxonomy.mappings.source.index');
        Route::get('mappings/platforms', 'platformMappings')->name('admin.catalog.taxonomy.mappings.platform.index');
        Route::get('mappings/manage', 'mappingForm')->name('admin.catalog.taxonomy.mappings.form');
        Route::get('reviews', 'reviews')->name('admin.catalog.taxonomy.reviews.index');
        Route::post('mappings', 'storeMapping')->name('admin.catalog.taxonomy.mappings.store');
        Route::put('mappings/{id}/details', 'updateMapping')->whereNumber('id')->name('admin.catalog.taxonomy.mappings.update');
        Route::put('mappings/{id}', 'reviewMapping')->whereNumber('id')->name('admin.catalog.taxonomy.mappings.review');
        Route::delete('mappings/{id}', 'destroyMapping')->whereNumber('id')->name('admin.catalog.taxonomy.mappings.delete');
        Route::put('assignments/{id}', 'reviewAssignment')->whereNumber('id')->name('admin.catalog.taxonomy.assignments.review');
    });
