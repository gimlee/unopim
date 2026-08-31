<x-admin::layouts>
    <x-slot:title>Exchange Rates</x-slot>

    <x-admin::page-header title="Exchange Rates / 汇率换算">
        <x-slot:actions>
            <form method="POST" action="{{ route('admin.settings.exchange_rates.refresh') }}">
                @csrf
                <button type="submit" class="primary-button">Update from Frankfurter</button>
            </form>
        </x-slot>
    </x-admin::page-header>

    @if (session('success'))
        <div class="mb-4 rounded bg-green-100 p-4 text-green-800">{{ session('success') }}</div>
    @endif

    @if (session('error') || $refreshError)
        <div class="mb-4 rounded bg-red-100 p-4 text-red-800">{{ session('error') ?: $refreshError }}</div>
    @endif

    @php
        $rates = collect($exchangeRates['rates'] ?? []);
        $currencies = $exchangeRates['currencies'] ?? ['CNY'];
        $matrix = $exchangeRates['real_matrix'] ?? [];
        $currencyNames = config('exchange-rates.currency_names', []);
        $currencyLabel = static fn (string $currency): string => isset($currencyNames[$currency])
            ? "{$currency}（{$currencyNames[$currency]}）"
            : $currency;
    @endphp

    <div class="grid gap-4 xl:grid-cols-[2fr_1fr]">
        <div class="rounded bg-white p-4 shadow dark:bg-cherry-900">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">CNY-based rates</h2>
                <p class="text-sm text-gray-500">All values mean 1 CNY = N target currency. Automatically checked every 3 hours.</p>
            </div>

            <form method="POST" action="{{ route('admin.settings.exchange_rates.update') }}">
                @csrf
                @method('PUT')

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b text-gray-600 dark:text-gray-300">
                            <tr>
                                <th class="p-3">Currency</th>
                                <th class="p-3">Frankfurter rate</th>
                                <th class="p-3">Selling rate</th>
                                <th class="p-3">Updated at</th>
                                <th class="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rates as $rate)
                                <tr class="border-b dark:border-cherry-800">
                                    <td class="p-3 font-semibold">{{ $currencyLabel($rate['currency']) }}</td>
                                    <td class="p-3">{{ rtrim(rtrim(number_format((float) $rate['real_rate'], 8, '.', ''), '0'), '.') }}</td>
                                    <td class="p-3">
                                        <input
                                            class="w-44 rounded border px-3 py-2 dark:bg-cherry-800"
                                            type="number"
                                            min="0.00000001"
                                            step="0.00000001"
                                            name="selling_rates[{{ $rate['currency'] }}]"
                                            value="{{ $rate['selling_rate'] }}"
                                            required
                                        >
                                    </td>
                                    <td class="p-3 whitespace-nowrap">
                                        {{ ! empty($rate['fetched_at'])
                                            ? \Carbon\CarbonImmutable::parse($rate['fetched_at'])->timezone(config('app.timezone'))->format('Y-m-d H:i:s')
                                            : $rate['rate_date'] }}
                                    </td>
                                    <td class="p-3">
                                        @if ($rate['fallback_used'])
                                            <span class="text-amber-600">one-day fallback</span>
                                        @elseif ($rate['below_real_rate'])
                                            <span class="text-red-600">selling rate below real rate</span>
                                        @elseif ($rate['selling_is_custom'])
                                            <span class="text-blue-600">custom</span>
                                        @else
                                            <span class="text-green-600">real rate</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="p-6 text-center text-gray-500">No exchange-rate data. Use “Update from Frankfurter”.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($rates->isNotEmpty())
                    <button type="submit" class="primary-button mt-4">Save selling rates</button>
                @endif
            </form>
        </div>

        <div class="rounded bg-white p-4 shadow dark:bg-cherry-900">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Currency converter</h2>
            <p class="mb-4 text-sm text-gray-500">在任意币种中输入金额，其他币种会按最新 Frankfurter 实时汇率自动换算。</p>

            <div class="flex flex-col gap-3" id="exchange-converter">
                @foreach ($currencies as $currency)
                    @php
                        $baseRate = $matrix['CNY'][$currency] ?? null;
                    @endphp
                    <label class="block rounded-lg border border-gray-200 bg-gray-50 p-3 transition-colors focus-within:border-primary-500 focus-within:bg-white dark:border-cherry-700 dark:bg-cherry-800">
                        <span class="mb-2 flex items-center justify-between gap-3">
                            <span class="font-semibold text-gray-800 dark:text-white">{{ $currencyLabel($currency) }}</span>
                            <span class="text-xs text-gray-500">1 CNY = {{ $baseRate ? rtrim(rtrim(number_format((float) $baseRate, 8, '.', ''), '0'), '.') : '—' }} {{ $currency }}</span>
                        </span>
                        <input
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-right text-lg font-semibold text-gray-800 outline-none focus:border-primary-500 dark:border-cherry-600 dark:bg-cherry-900 dark:text-white"
                            type="number"
                            min="0"
                            step="any"
                            inputmode="decimal"
                            value="{{ $currency === 'CNY' ? '100' : '' }}"
                            data-converter-currency="{{ $currency }}"
                            aria-label="{{ $currencyLabel($currency) }} amount"
                        >
                    </label>
                @endforeach
            </div>
        </div>
    </div>

    @pushOnce('scripts')
        <script>
            (() => {
                const initialize = () => {
                    const matrix = @json($matrix);
                    const fields = Array.from(document.querySelectorAll('[data-converter-currency]'));

                    const formatValue = (value) => {
                        if (! Number.isFinite(value)) {
                            return '';
                        }

                        return String(Number(value.toFixed(6)));
                    };

                    const update = (source) => {
                        const sourceCurrency = source.dataset.converterCurrency;
                        const sourceValue = Number(source.value);

                        fields.forEach((target) => {
                            if (target === source) {
                                return;
                            }

                            const targetCurrency = target.dataset.converterCurrency;
                            const rate = matrix[sourceCurrency]?.[targetCurrency];
                            target.value = source.value !== '' && Number.isFinite(sourceValue) && rate
                                ? formatValue(sourceValue * rate)
                                : '';
                        });
                    };

                    fields.forEach((field) => field.addEventListener('input', () => update(field)));

                    const initial = fields.find((field) => field.dataset.converterCurrency === 'CNY');

                    if (initial) {
                        update(initial);
                    }
                };

                if (document.readyState === 'complete') {
                    window.setTimeout(initialize, 0);
                } else {
                    window.addEventListener('load', () => window.setTimeout(initialize, 0), { once: true });
                }
            })();
        </script>
    @endPushOnce
</x-admin::layouts>
