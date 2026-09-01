<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_sync_states', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('version');
            $table->json('metadata')->nullable();
            $table->timestampTz('synced_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_sync_states');
    }
};
