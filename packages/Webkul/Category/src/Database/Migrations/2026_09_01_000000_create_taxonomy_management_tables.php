<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('taxonomy_type', 32)->default('legacy')->index();
            $table->boolean('is_assignable')->default(true)->index();
            $table->string('status', 32)->default('active')->index();
            $table->unsignedInteger('replaced_by_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('source_platform', 64)->nullable()->index();
            $table->string('source_external_id')->nullable();
            $table->text('source_path')->nullable();
            $table->text('source_url')->nullable();

            $table->foreign('replaced_by_id')->references('id')->on('categories')->nullOnDelete();
            $table->index(['source_platform', 'source_external_id'], 'categories_source_reference_idx');
        });

        Schema::create('category_aliases', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('category_id');
            $table->string('locale', 16)->default('zh_CN');
            $table->string('alias');
            $table->string('normalized_alias');
            $table->string('source', 32)->default('manual');
            $table->timestamps();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->unique(['category_id', 'locale', 'normalized_alias'], 'category_alias_unique');
            $table->index(['locale', 'normalized_alias'], 'category_alias_lookup_idx');
        });

        Schema::create('category_classification_rules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('category_id');
            $table->string('rule_type', 32)->default('keyword');
            $table->string('field', 64)->default('any');
            $table->string('operator', 32)->default('contains');
            $table->text('value');
            $table->decimal('weight', 8, 4)->default(1);
            $table->boolean('status')->default(true);
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->index(['status', 'position']);
        });

        Schema::create('platform_taxonomies', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('platform', 64)->index();
            $table->string('region', 16)->nullable()->index();
            $table->string('locale', 16)->nullable();
            $table->string('version')->default('current');
            $table->text('source_url')->nullable();
            $table->timestampTz('synced_at')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
            $table->unique(['platform', 'region', 'version'], 'platform_taxonomy_version_unique');
        });

        Schema::create('platform_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_taxonomy_id')->constrained('platform_taxonomies')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('platform_categories')->cascadeOnDelete();
            $table->string('external_id');
            $table->string('name');
            $table->text('path');
            $table->boolean('is_leaf')->default(true);
            $table->boolean('enabled')->default(true);
            $table->json('raw_payload')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->timestamps();
            $table->unique(['platform_taxonomy_id', 'external_id'], 'platform_category_external_unique');
            $table->index(['platform_taxonomy_id', 'path'], 'platform_category_path_idx');
        });

        Schema::create('category_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('category_id');
            $table->foreignId('platform_category_id')->constrained('platform_categories')->cascadeOnDelete();
            $table->string('mapping_type', 32)->default('exact');
            $table->json('conditions')->nullable();
            $table->integer('priority')->default(0);
            $table->string('status', 32)->default('draft')->index();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('valid_from')->nullable();
            $table->timestampTz('valid_to')->nullable();
            $table->timestamps();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('admins')->nullOnDelete();
            $table->unique(['category_id', 'platform_category_id'], 'category_platform_mapping_unique');
            $table->index(['category_id', 'status', 'priority'], 'category_mapping_resolve_idx');
        });

        Schema::create('product_category_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('category_id');
            $table->string('role', 32)->default('primary');
            $table->string('status', 32)->default('proposed')->index();
            $table->string('method', 32)->default('manual');
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('evidence')->nullable();
            $table->string('taxonomy_version')->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestamps();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('admins')->nullOnDelete();
            $table->unique(['product_id', 'category_id', 'role'], 'product_category_assignment_unique');
            $table->index(['product_id', 'role', 'status'], 'product_primary_category_idx');
        });

        Schema::create('platform_category_attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_category_id')->constrained('platform_categories')->cascadeOnDelete();
            $table->string('external_id');
            $table->string('name');
            $table->string('type', 64)->nullable();
            $table->boolean('required')->default(false);
            $table->json('rules')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->unique(['platform_category_id', 'external_id'], 'platform_category_attribute_unique');
        });

        Schema::create('category_attribute_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('category_id');
            $table->foreignId('platform_category_attribute_id')->constrained('platform_category_attributes')->cascadeOnDelete();
            $table->string('source_attribute_code');
            $table->string('status', 32)->default('active');
            $table->json('transform')->nullable();
            $table->timestamps();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->unique(['category_id', 'platform_category_attribute_id'], 'category_attribute_mapping_unique');
        });

        DB::table('categories')->whereNull('parent_id')->update([
            'taxonomy_type' => 'container',
            'is_assignable' => false,
        ]);
        DB::table('categories')->where('code', 'source_1688')->update([
            'taxonomy_type'   => 'source',
            'is_assignable'   => false,
            'source_platform' => '1688',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('category_attribute_mappings');
        Schema::dropIfExists('platform_category_attributes');
        Schema::dropIfExists('product_category_assignments');
        Schema::dropIfExists('category_mappings');
        Schema::dropIfExists('platform_categories');
        Schema::dropIfExists('platform_taxonomies');
        Schema::dropIfExists('category_classification_rules');
        Schema::dropIfExists('category_aliases');

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropForeign(['replaced_by_id']);
            $table->dropIndex('categories_source_reference_idx');
            $table->dropColumn([
                'taxonomy_type', 'is_assignable', 'status', 'replaced_by_id', 'sort_order',
                'source_platform', 'source_external_id', 'source_path', 'source_url',
            ]);
        });
    }
};
