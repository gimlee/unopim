<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_content_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('platform', 32)->default('tiktok');
            $table->string('region', 16)->nullable();
            $table->string('locale', 16);
            $table->json('matched_terms');
            $table->json('original_content');
            $table->json('optimized_content');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('method', 32)->default('ai');
            $table->timestamps();

            $table->index(['product_id', 'created_at'], 'product_content_revisions_product_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_content_revisions');
    }
};
