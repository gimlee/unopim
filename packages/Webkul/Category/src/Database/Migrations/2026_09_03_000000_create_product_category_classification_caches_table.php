<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_category_classification_caches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('product_id');
            $table->string('method', 16);
            $table->string('platform', 64)->default('tiktok');
            $table->unsignedInteger('standard_category_id');
            $table->foreignId('platform_category_id')->nullable()->constrained('platform_categories')->nullOnDelete();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->string('status', 32)->default('ready');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('input_hash', 64)->nullable();
            $table->text('reason')->nullable();
            $table->json('evidence')->nullable();
            $table->timestampTz('generated_at');
            $table->timestampTz('applied_at')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('standard_category_id')->references('id')->on('categories')->restrictOnDelete();
            $table->unique(['product_id', 'method', 'platform'], 'product_category_classification_cache_unique');
            $table->index(['product_id', 'status'], 'product_category_classification_cache_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_category_classification_caches');
    }
};
