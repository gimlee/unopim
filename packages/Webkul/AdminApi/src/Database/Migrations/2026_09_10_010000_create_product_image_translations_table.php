<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_image_translations')) {
            return;
        }

        Schema::create('product_image_translations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('product_id');
            $table->string('sku', 128)->index();
            $table->string('region', 16)->index();
            $table->string('locale', 16);
            $table->string('image_type', 32)->default('gallery');
            $table->text('original_url');
            $table->text('translated_url');
            $table->text('local_path')->nullable();
            $table->string('variant_sku', 128)->nullable()->index();
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->index(['product_id', 'region'], 'product_image_translations_product_region_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_image_translations');
    }
};
