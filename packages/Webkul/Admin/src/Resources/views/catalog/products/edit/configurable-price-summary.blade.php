@php
    $currencyNames = [
        'CNY' => '人民币',
        'MYR' => '马来西亚令吉',
        'THB' => '泰铢',
        'USD' => '美元',
    ];
    $ranges = collect($summary['ranges'] ?? []);
    $variants = collect($summary['variants'] ?? []);
@endphp

<div class="relative rounded bg-white box-shadow dark:bg-cherry-900">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b p-4 dark:border-cherry-800">
        <div class="flex flex-col gap-1">
            <p class="text-base font-semibold text-gray-800 dark:text-white">
                SKU 价格
            </p>

            <p class="text-xs font-medium text-gray-500 dark:text-gray-300">
                Configurable 商品不使用单一固定售价；价格由各个 Simple SKU 独立维护。
            </p>
        </div>

        @if ($ranges->isNotEmpty())
            <div class="flex flex-wrap justify-end gap-2">
                @foreach ($ranges as $range)
                    @php
                        $currency = $range['currency'];
                        $currencyLabel = isset($currencyNames[$currency])
                            ? $currency.'('.$currencyNames[$currency].')'
                            : $currency;
                        $samePrice = abs((float) $range['max'] - (float) $range['min']) < 0.00001;
                    @endphp

                    <span class="rounded-md bg-primary-50 px-2.5 py-1 text-sm font-semibold text-primary-700 dark:bg-cherry-800 dark:text-primary-300">
                        {{ $currencyLabel }}
                        {{ number_format((float) $range['min'], 2) }}
                        @unless ($samePrice)
                            – {{ number_format((float) $range['max'], 2) }}
                        @endunless
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    @if ($variants->isEmpty())
        <p class="p-4 text-sm text-gray-500 dark:text-gray-300">
            当前商品还没有可售 SKU，因此暂无 SKU 价格。
        </p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-gray-50 text-xs text-gray-500 dark:border-cherry-800 dark:bg-cherry-800 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">SKU</th>
                        <th class="px-4 py-2.5 font-medium">价格</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($variants as $variant)
                        <tr class="border-b last:border-b-0 dark:border-cherry-800">
                            <td class="px-4 py-2.5 font-medium text-gray-800 dark:text-white">
                                {{ $variant['sku'] }}
                            </td>
                            <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300">
                                {{ $variant['price'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
