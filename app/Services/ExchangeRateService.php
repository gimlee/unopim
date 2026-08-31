<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ExchangeRateService
{
    public function baseCurrency(): string
    {
        return strtoupper((string) config('exchange-rates.base_currency', 'CNY'));
    }

    /** @return list<string> */
    public function quoteCurrencies(): array
    {
        return array_values(array_unique(array_map(
            static fn (mixed $currency): string => strtoupper(trim((string) $currency)),
            config('exchange-rates.quote_currencies', ['USD', 'MYR', 'THB'])
        )));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array{date: string, rate: float}>
     */
    public function normalizeRates(array $rows): array
    {
        $grouped = array_fill_keys($this->quoteCurrencies(), []);

        foreach ($rows as $row) {
            $quote = strtoupper((string) ($row['quote'] ?? ''));
            $rate = filter_var($row['rate'] ?? null, FILTER_VALIDATE_FLOAT);

            if (! isset($grouped[$quote]) || $rate === false || $rate <= 0) {
                continue;
            }

            try {
                $date = CarbonImmutable::parse((string) ($row['date'] ?? ''))->startOfDay();
            } catch (Throwable) {
                continue;
            }

            $grouped[$quote][] = ['date' => $date, 'rate' => (float) $rate];
        }

        $normalized = [];

        foreach ($grouped as $quote => $values) {
            if ($values === []) {
                continue;
            }

            $latestDate = collect($values)->max('date');
            $normalized[$quote] = [
                'date' => $latestDate->toDateString(),
                'rate' => array_sum(array_column($values, 'rate')) / count($values),
            ];
        }

        return $normalized;
    }

    /** @return array<string, mixed> */
    public function refresh(bool $force = false): array
    {
        $now = CarbonImmutable::now('UTC');
        $latestFetch = DB::table('exchange_rates')->max('fetched_at');
        $freshHours = max(1, (int) config('exchange-rates.update_hours', 3));

        if (! $force && $latestFetch && CarbonImmutable::parse($latestFetch)->gt($now->subHours($freshHours))) {
            return ['updated' => false, 'reason' => 'FRESH_CACHE', 'fetched_at' => $latestFetch];
        }

        $params = [
            'base'   => $this->baseCurrency(),
            'quotes' => implode(',', $this->quoteCurrencies()),
        ];
        $fallbackUsed = false;

        try {
            $rates = $this->fetchRates($params);

            if (count($rates) !== count($this->quoteCurrencies())) {
                throw new RuntimeException('Frankfurter returned an incomplete latest-rate response.');
            }
        } catch (Throwable $latestError) {
            $fallbackUsed = true;

            try {
                $rates = $this->fetchRates([
                    ...$params,
                    'from' => $now->subDay()->toDateString(),
                    'to'   => $now->toDateString(),
                ]);
            } catch (Throwable) {
                $rates = [];
            }

            if (count($rates) !== count($this->quoteCurrencies())) {
                $rates = $this->cachedOneDayAverages($now);
            }

            if (count($rates) !== count($this->quoteCurrencies())) {
                throw new RuntimeException(
                    'Frankfurter latest and one-day fallback rates are unavailable: '.$latestError->getMessage(),
                    previous: $latestError
                );
            }
        }

        DB::transaction(function () use ($rates, $fallbackUsed, $now): void {
            foreach ($rates as $quote => $rate) {
                DB::table('exchange_rates')->upsert([[
                    'id'              => (string) Str::uuid(),
                    'rate_date'       => $rate['date'],
                    'base_currency'   => $this->baseCurrency(),
                    'quote_currency'  => $quote,
                    'real_rate'       => $rate['rate'],
                    'source'          => 'FRANKFURTER',
                    'fallback_used'   => $fallbackUsed,
                    'fetched_at'      => $now,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]], ['rate_date', 'base_currency', 'quote_currency'], [
                    'real_rate', 'source', 'fallback_used', 'fetched_at', 'updated_at',
                ]);

                DB::table('exchange_rate_settings')->insertOrIgnore([
                    'quote_currency' => $quote,
                    'selling_rate'   => null,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }

            DB::table('exchange_rates')
                ->where('rate_date', '<', $now->subDays((int) config('exchange-rates.retention_days', 31))->toDateString())
                ->delete();
        });

        return [
            'updated'       => true,
            'fallback_used' => $fallbackUsed,
            'fetched_at'    => $now->toIso8601String(),
            'count'         => count($rates),
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $rates = [];
        $realByCurrency = [$this->baseCurrency() => 1.0];
        $sellingByCurrency = [$this->baseCurrency() => 1.0];

        foreach ($this->quoteCurrencies() as $quote) {
            $row = DB::table('exchange_rates')
                ->where('base_currency', $this->baseCurrency())
                ->where('quote_currency', $quote)
                ->orderByDesc('rate_date')
                ->orderByDesc('fetched_at')
                ->first();

            if (! $row) {
                continue;
            }

            $setting = DB::table('exchange_rate_settings')->where('quote_currency', $quote)->first();
            $realRate = (float) $row->real_rate;
            $customSellingRate = $setting?->selling_rate;
            $sellingRate = $customSellingRate === null ? $realRate : (float) $customSellingRate;
            $realByCurrency[$quote] = $realRate;
            $sellingByCurrency[$quote] = $sellingRate;
            $rates[] = [
                'currency'           => $quote,
                'real_rate'          => $realRate,
                'selling_rate'       => $sellingRate,
                'selling_is_custom'  => $customSellingRate !== null,
                'below_real_rate'    => $sellingRate < $realRate,
                'rate_date'          => $row->rate_date,
                'source'             => $row->source,
                'fallback_used'      => (bool) $row->fallback_used,
                'fetched_at'         => $row->fetched_at,
                'selling_updated_at' => $setting?->updated_at,
            ];
        }

        return [
            'base_currency'  => $this->baseCurrency(),
            'currencies'     => array_keys($realByCurrency),
            'rates'          => $rates,
            'real_matrix'    => $this->matrix($realByCurrency),
            'selling_matrix' => $this->matrix($sellingByCurrency),
        ];
    }

    /** @param array<string, float|int|string|null> $rates */
    public function saveSellingRates(array $rates): void
    {
        $now = CarbonImmutable::now('UTC');

        foreach ($this->quoteCurrencies() as $quote) {
            if (! array_key_exists($quote, $rates)) {
                continue;
            }

            $value = $rates[$quote];
            $numeric = filter_var($value, FILTER_VALIDATE_FLOAT);

            if ($numeric === false || $numeric <= 0) {
                throw new RuntimeException("{$quote} selling rate must be greater than zero.");
            }

            DB::table('exchange_rate_settings')->upsert([[
                'quote_currency' => $quote,
                'selling_rate'   => (float) $numeric,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]], ['quote_currency'], ['selling_rate', 'updated_at']);
        }
    }

    /** @param array<string, float> $rates */
    public function convert(float $amount, string $from, string $to, array $rates): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        $rates[$this->baseCurrency()] = 1.0;

        if (! isset($rates[$from], $rates[$to]) || $rates[$from] <= 0 || $rates[$to] <= 0) {
            throw new RuntimeException("Exchange rate is unavailable for {$from} or {$to}.");
        }

        return $amount / $rates[$from] * $rates[$to];
    }

    /** @param array<string, string> $params */
    protected function fetchRates(array $params): array
    {
        $request = Http::acceptJson()->timeout(30)->retry(2, 500);
        $caBundle = (string) config('exchange-rates.ca_bundle', '');

        if ($caBundle !== '' && is_file($caBundle)) {
            $request = $request->withOptions(['verify' => $caBundle]);
        }

        $response = $request
            ->get((string) config('exchange-rates.frankfurter_url'), $params)
            ->throw();
        $payload = $response->json();

        return $this->normalizeRates(is_array($payload) ? $payload : []);
    }

    /** @return array<string, array{date: string, rate: float}> */
    protected function cachedOneDayAverages(CarbonImmutable $now): array
    {
        $rows = DB::table('exchange_rates')
            ->selectRaw('quote_currency, MAX(rate_date) AS rate_date, AVG(real_rate) AS real_rate')
            ->where('base_currency', $this->baseCurrency())
            ->where('fetched_at', '>=', $now->subDay())
            ->groupBy('quote_currency')
            ->get();

        $rates = [];

        foreach ($rows as $row) {
            $rates[(string) $row->quote_currency] = [
                'date' => (string) $row->rate_date,
                'rate' => (float) $row->real_rate,
            ];
        }

        return $rates;
    }

    /**
     * @param  array<string, float>  $rates
     * @return array<string, array<string, float>>
     */
    protected function matrix(array $rates): array
    {
        $matrix = [];

        foreach ($rates as $source => $sourceRate) {
            foreach ($rates as $target => $targetRate) {
                $matrix[$source][$target] = $targetRate / $sourceRate;
            }
        }

        return $matrix;
    }
}
