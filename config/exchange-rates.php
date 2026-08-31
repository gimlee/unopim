<?php

return [
    'base_currency'    => 'CNY',
    'currency_names'   => [
        'CNY' => '人民币',
        'USD' => '美元',
        'MYR' => '马来西亚林吉特',
        'THB' => '泰铢',
    ],
    'quote_currencies' => array_values(array_filter(array_map(
        static fn (string $currency): string => strtoupper(trim($currency)),
        explode(',', (string) env('EXCHANGE_RATE_CURRENCIES', 'USD,MYR,THB'))
    ))),
    'frankfurter_url' => env('FRANKFURTER_URL', 'https://api.frankfurter.dev/v2/rates'),
    'ca_bundle'       => env('EXCHANGE_RATE_CA_BUNDLE') ?: base_path('vendor/composer/ca-bundle/res/cacert.pem'),
    'update_hours'    => max(1, (int) env('EXCHANGE_RATE_UPDATE_HOURS', 3)),
    'retention_days'  => 31,
];
