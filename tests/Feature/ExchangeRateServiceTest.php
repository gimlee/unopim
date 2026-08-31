<?php

use App\Services\ExchangeRateService;
use Tests\TestCase;

uses(TestCase::class);

it('normalizes Frankfurter rows and averages a fallback period', function () {
    config()->set('exchange-rates.quote_currencies', ['USD', 'MYR', 'THB']);
    $service = app(ExchangeRateService::class);

    $rates = $service->normalizeRates([
        ['date' => '2026-08-30', 'quote' => 'USD', 'rate' => 0.14],
        ['date' => '2026-08-31', 'quote' => 'USD', 'rate' => 0.16],
        ['date' => '2026-08-31', 'quote' => 'MYR', 'rate' => 0.60],
        ['date' => '2026-08-31', 'quote' => 'THB', 'rate' => 4.50],
        ['date' => 'invalid', 'quote' => 'THB', 'rate' => -1],
    ]);

    expect($rates['USD']['date'])->toBe('2026-08-31');
    expect($rates['USD']['rate'])->toEqualWithDelta(0.15, 0.00000001);
    expect($rates['MYR']['rate'])->toEqualWithDelta(0.60, 0.00000001);
    expect($rates['THB']['rate'])->toEqualWithDelta(4.50, 0.00000001);
});

it('converts currencies through the CNY base rate', function () {
    $service = app(ExchangeRateService::class);

    expect($service->convert(100, 'MYR', 'THB', [
        'CNY' => 1.0,
        'MYR' => 0.60,
        'THB' => 4.50,
    ]))->toEqualWithDelta(750.0, 0.00000001);
});
