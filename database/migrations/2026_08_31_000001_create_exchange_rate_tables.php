<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('rate_date');
            $table->string('base_currency', 3)->default('CNY');
            $table->string('quote_currency', 3);
            $table->decimal('real_rate', 20, 8);
            $table->string('source', 32)->default('FRANKFURTER');
            $table->boolean('fallback_used')->default(false);
            $table->timestampTz('fetched_at');
            $table->timestamps();

            $table->unique(['rate_date', 'base_currency', 'quote_currency'], 'exchange_rates_pair_date_unique');
            $table->index(['base_currency', 'quote_currency', 'rate_date'], 'exchange_rates_pair_date_index');
        });

        Schema::create('exchange_rate_settings', function (Blueprint $table): void {
            $table->string('quote_currency', 3)->primary();
            $table->decimal('selling_rate', 20, 8)->nullable();
            $table->timestamps();
        });

        foreach (config('exchange-rates.quote_currencies', ['USD', 'MYR', 'THB']) as $currency) {
            DB::table('exchange_rate_settings')->insertOrIgnore([
                'quote_currency' => $currency,
                'selling_rate'   => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rate_settings');
        Schema::dropIfExists('exchange_rates');
    }
};
