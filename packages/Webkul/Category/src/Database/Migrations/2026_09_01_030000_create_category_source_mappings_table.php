<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_source_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('category_id');
            $table->unsignedInteger('source_category_id');
            $table->string('source_platform', 64)->index();
            $table->string('mapping_type', 32)->default('exact');
            $table->string('status', 32)->default('draft')->index();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('source_category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('admins')->nullOnDelete();
            $table->unique(['category_id', 'source_category_id'], 'category_source_mapping_unique');
            $table->index(['source_platform', 'status'], 'category_source_mapping_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_source_mappings');
    }
};
