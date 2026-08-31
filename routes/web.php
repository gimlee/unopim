<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded via the "web" group configured in bootstrap/app.php.
| UnoPim's own routes are registered by each Concord package provider.
|
*/

use App\Http\Controllers\Admin\ExchangeRateController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['admin'], 'prefix' => config('app.admin_url')], function (): void {
    Route::prefix('settings/exchange-rates')->controller(ExchangeRateController::class)->group(function (): void {
        Route::get('', 'index')->name('admin.settings.exchange_rates.index');
        Route::post('refresh', 'refresh')->name('admin.settings.exchange_rates.refresh');
        Route::put('selling-rates', 'update')->name('admin.settings.exchange_rates.update');
    });
});
