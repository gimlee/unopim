<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_image_translation_jobs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedInteger('product_id');
            $table->string('status', 16)->default('queued')->index();
            $table->json('payload');
            $table->json('results')->nullable();
            $table->json('errors')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->index(['product_id', 'created_at'], 'image_translation_jobs_product_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_image_translation_jobs');
    }
};
