<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_listing_histories', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('product_id');
            $table->string('sku', 128)->index();
            $table->string('platform', 32)->default('tiktok');
            $table->string('region', 16);
            $table->string('attempt_id', 64);
            $table->string('listing_type', 16);
            $table->string('status', 64)->index();
            $table->boolean('published')->default(false);
            $table->text('draft_url')->nullable();
            $table->text('seller_url')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['product_id', 'platform', 'attempt_id'], 'listing_history_product_attempt_unique');
            $table->index(['product_id', 'completed_at', 'id'], 'listing_history_product_latest_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_listing_histories');
    }
};
