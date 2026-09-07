<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_content_revisions', function (Blueprint $table): void {
            $table->unsignedBigInteger('template_id')->nullable()->after('method');
            $table->string('template_title')->nullable()->after('template_id');
            $table->text('prompt_snapshot')->nullable()->after('template_title');
        });
    }

    public function down(): void
    {
        Schema::table('product_content_revisions', function (Blueprint $table): void {
            $table->dropColumn(['template_id', 'template_title', 'prompt_snapshot']);
        });
    }
};
