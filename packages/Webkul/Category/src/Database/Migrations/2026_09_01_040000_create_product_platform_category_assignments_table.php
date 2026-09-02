<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_platform_category_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('product_id');
            $table->string('platform', 64);
            $table->foreignId('platform_category_id')->constrained('platform_categories')->restrictOnDelete();
            $table->string('status', 32)->default('confirmed')->index();
            $table->string('method', 32)->default('manual');
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('evidence')->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('admins')->nullOnDelete();
            $table->unique(['product_id', 'platform'], 'product_platform_category_unique');
            $table->index(['platform', 'platform_category_id'], 'product_platform_category_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_platform_category_assignments');
    }
};
