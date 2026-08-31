<?php

namespace App\Console\Commands;

use App\Services\ExchangeRateService;
use Illuminate\Console\Command;
use Throwable;

class RefreshExchangeRates extends Command
{
    protected $signature = 'unopim:exchange-rates:refresh {--force : Ignore the three-hour freshness window}';

    protected $description = 'Refresh CNY-based USD, MYR and THB rates from Frankfurter';

    public function handle(ExchangeRateService $service): int
    {
        try {
            $result = $service->refresh((bool) $this->option('force'));
            $this->info(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
