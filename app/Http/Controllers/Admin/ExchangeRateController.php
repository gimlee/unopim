<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ExchangeRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ExchangeRateController extends Controller
{
    public function index(ExchangeRateService $service): View
    {
        $refreshError = null;

        try {
            $service->refresh();
        } catch (Throwable $error) {
            $refreshError = $error->getMessage();
        }

        return view('admin::settings.exchange-rates.index', [
            'exchangeRates' => $service->payload(),
            'refreshError'  => $refreshError,
        ]);
    }

    public function refresh(ExchangeRateService $service): RedirectResponse
    {
        try {
            $result = $service->refresh(true);

            return back()->with('success', 'Frankfurter exchange rates updated: '.($result['count'] ?? 0).' currencies.');
        } catch (Throwable $error) {
            return back()->with('error', $error->getMessage());
        }
    }

    public function update(Request $request, ExchangeRateService $service): RedirectResponse
    {
        $validated = $request->validate([
            'selling_rates'   => ['required', 'array'],
            'selling_rates.*' => ['required', 'numeric', 'gt:0'],
        ]);

        $service->saveSellingRates($validated['selling_rates']);

        return back()->with('success', 'Selling exchange rates saved.');
    }
}
