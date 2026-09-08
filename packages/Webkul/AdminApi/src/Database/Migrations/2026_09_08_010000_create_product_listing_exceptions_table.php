<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_listing_exceptions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('product_id');
            $table->string('sku', 128)->index();
            $table->string('platform', 32)->default('tiktok');
            $table->string('region', 16)->nullable();
            $table->string('attempt_id', 64);
            $table->string('event_key', 128);
            $table->string('exception_type', 64)->index();
            $table->string('stage', 128)->nullable();
            $table->string('severity', 16)->default('warning');
            $table->text('message');
            $table->boolean('requires_manual')->default(false);
            $table->boolean('blocking')->default(false);
            $table->json('details')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['product_id', 'attempt_id', 'event_key'], 'listing_exceptions_attempt_event_unique');
            $table->index(['product_id', 'resolved_at'], 'listing_exceptions_product_resolved_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_listing_exceptions');
    }
};
