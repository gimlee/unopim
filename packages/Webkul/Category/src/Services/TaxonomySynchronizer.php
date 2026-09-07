<?php

namespace Webkul\Category\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webkul\Category\Models\Category;
use Webkul\Category\Models\CategoryAlias;
use Webkul\Category\Models\CategoryAttributeMapping;
use Webkul\Category\Models\CategoryClassificationRule;
use Webkul\Category\Models\CategoryMapping;
use Webkul\Category\Models\CategorySourceMapping;
use Webkul\Category\Models\PlatformCategory;
use Webkul\Category\Models\PlatformCategoryAttribute;
use Webkul\Category\Models\PlatformTaxonomy;
use Webkul\Category\Models\ProductCategoryAssignment;
use Webkul\Category\Models\ProductPlatformCategoryAssignment;
use Webkul\Product\Models\Product;

class TaxonomySynchronizer
{
    public function sync(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $report = [
                'categories' => $this->syncCategories(
                    $payload['categories'] ?? [],
                    (bool) ($payload['fix_tree'] ?? true)
                ),
                'platform_taxonomies' => $this->syncPlatformTaxonomies($payload['platform_taxonomies'] ?? []),
                'source_mappings'     => $this->syncSourceMappings($payload['source_mappings'] ?? []),
                'assignments'         => $this->syncAssignments($payload['assignments'] ?? []),
            ];

            if (! empty($payload['migrate_source_category'])) {
                $report['migrated_products'] = $this->migrateSourceCategory(
                    (string) $payload['migrate_source_category'],
                    (string) ($payload['fallback_category'] ?? 'std_uncategorized')
                );
            }

            if (! empty($payload['remove_categories'])) {
                $report['removed_categories'] = $this->removeCategories(
                    $payload['remove_categories'],
                    (string) ($payload['fallback_category'] ?? 'std_uncategorized')
                );
            }

            if (! empty($payload['sync_state']['key']) && ! empty($payload['sync_state']['version'])) {
                DB::table('taxonomy_sync_states')->updateOrInsert(
                    ['key' => (string) $payload['sync_state']['key']],
                    [
                        'version'    => (string) $payload['sync_state']['version'],
                        'metadata'   => json_encode($payload['sync_state']['metadata'] ?? [], JSON_UNESCAPED_UNICODE),
                        'synced_at'  => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            return $report;
        });
    }

    protected function syncCategories(array $definitions, bool $fixTree): array
    {
        $created = 0;
        $updated = 0;
        $seen = [];
        $unchanged = 0;

        foreach ($definitions as $definition) {
            $code = trim((string) ($definition['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $parentCode = $definition['parent'] ?? null;
            $parentId = $parentCode ? Category::where('code', $parentCode)->value('id') : null;
            if ($parentCode && ! $parentId) {
                throw new \InvalidArgumentException("Category parent does not exist: {$parentCode}");
            }

            $category = Category::firstOrNew(['code' => $code]);
            $wasNew = ! $category->exists;
            $additional = $category->additional_data ?: [];
            if ($wasNew || ! $category->sync_locked) {
                foreach (($definition['labels'] ?? []) as $locale => $label) {
                    Arr::set($additional, "locale_specific.{$locale}.name", (string) $label);
                }
            }
            $managed = [
                'source_platform'    => $definition['source_platform'] ?? null,
                'source_external_id' => $definition['source_external_id'] ?? null,
                'source_path'        => $definition['source_path'] ?? null,
                'source_url'         => $definition['source_url'] ?? null,
            ];
            if ($wasNew || ! $category->sync_locked) {
                $managed += [
                    'parent_id'     => $parentId,
                    'taxonomy_type' => $definition['taxonomy_type'] ?? 'standard',
                    'is_assignable' => (bool) ($definition['is_assignable'] ?? false),
                    'status'        => $definition['status'] ?? 'active',
                    'sort_order'    => (int) ($definition['sort_order'] ?? 0),
                ];
            }
            $category->fill($managed);
            $category->additional_data = $additional;
            $dirty = $category->isDirty();
            $category->save();
            if ($wasNew) {
                $created++;
            } elseif ($dirty) {
                $updated++;
            } else {
                $unchanged++;
            }
            $seen[] = $code;

            foreach (array_values(array_unique($definition['aliases'] ?? [])) as $alias) {
                $normalized = Str::lower(preg_replace('/\s+/u', ' ', trim((string) $alias)) ?: '');
                if ($normalized === '') {
                    continue;
                }
                CategoryAlias::updateOrCreate(
                    ['category_id' => $category->id, 'locale' => 'zh_CN', 'normalized_alias' => $normalized],
                    ['alias' => $alias, 'source' => $definition['source_platform'] ?? 'sync']
                );
            }

            if (array_key_exists('classification_rules', $definition)) {
                $category->classificationRules()->delete();
                foreach ($definition['classification_rules'] ?: [] as $position => $rule) {
                    CategoryClassificationRule::create([
                        'category_id' => $category->id,
                        'rule_type'   => $rule['rule_type'] ?? 'keyword',
                        'field'       => $rule['field'] ?? 'any',
                        'operator'    => $rule['operator'] ?? 'contains',
                        'value'       => (string) ($rule['value'] ?? ''),
                        'weight'      => $rule['weight'] ?? 1,
                        'status'      => $rule['status'] ?? true,
                        'position'    => $position,
                    ]);
                }
            }
        }

        if ($fixTree && $definitions !== []) {
            Category::fixTree();
        }

        return [
            'created'   => $created,
            'updated'   => $updated,
            'unchanged' => $unchanged,
            'processed' => count(array_unique($seen)),
        ];
    }

    protected function syncPlatformTaxonomies(array $definitions): array
    {
        $taxonomies = 0;
        $categories = 0;
        $mappings = 0;
        $attributes = 0;
        $attributeMappings = 0;

        foreach ($definitions as $definition) {
            $taxonomy = PlatformTaxonomy::updateOrCreate(
                ['code' => (string) $definition['code']],
                [
                    'platform'   => (string) $definition['platform'],
                    'region'     => $definition['region'] ?? null,
                    'locale'     => $definition['locale'] ?? null,
                    'version'    => $definition['version'] ?? 'current',
                    'source_url' => $definition['source_url'] ?? null,
                    'synced_at'  => $definition['synced_at'] ?? now(),
                    'status'     => $definition['status'] ?? 'active',
                ]
            );
            $taxonomies++;
            $platformByExternalId = [];
            $seenExternalIds = [];
            foreach ($definition['categories'] ?? [] as $item) {
                $parentId = null;
                if (! empty($item['parent_external_id'])) {
                    $parentId = $platformByExternalId[(string) $item['parent_external_id']]
                        ?? PlatformCategory::where('platform_taxonomy_id', $taxonomy->id)
                            ->where('external_id', $item['parent_external_id'])
                            ->value('id');
                }
                $platformCategory = PlatformCategory::updateOrCreate(
                    [
                        'platform_taxonomy_id' => $taxonomy->id,
                        'external_id'          => (string) $item['external_id'],
                    ],
                    [
                        'parent_id'    => $parentId,
                        'name'         => (string) $item['name'],
                        'path'         => (string) ($item['path'] ?? $item['name']),
                        'is_leaf'      => (bool) ($item['is_leaf'] ?? true),
                        'enabled'      => (bool) ($item['enabled'] ?? true),
                        'raw_payload'  => $item['raw_payload'] ?? null,
                        'payload_hash' => $item['payload_hash'] ?? null,
                    ]
                );
                $platformByExternalId[(string) $item['external_id']] = $platformCategory->id;
                $seenExternalIds[] = (string) $item['external_id'];
                $categories++;

                $mappingDefinitions = $item['canonical_mappings'] ?? array_map(
                    fn ($code): array => [
                        'category_code' => $code,
                        'mapping_type'  => $item['mapping_type'] ?? 'exact',
                        'status'        => $item['mapping_status'] ?? 'confirmed',
                        'confidence'    => $item['confidence'] ?? 1,
                    ],
                    $item['canonical_codes'] ?? []
                );
                foreach ($mappingDefinitions as $mappingDefinition) {
                    $categoryId = Category::where('code', $mappingDefinition['category_code'] ?? null)->value('id');
                    if (! $categoryId) {
                        continue;
                    }
                    $mapping = CategoryMapping::firstOrNew(
                        ['category_id' => $categoryId, 'platform_category_id' => $platformCategory->id],
                    );
                    $incomingStatus = $mappingDefinition['status'] ?? 'draft';
                    if (! ($mapping->exists && $mapping->status === 'confirmed' && $incomingStatus !== 'confirmed')) {
                        $mapping->fill([
                            'mapping_type' => $mappingDefinition['mapping_type'] ?? 'exact',
                            'conditions'   => $mappingDefinition['conditions'] ?? null,
                            'priority'     => (int) ($mappingDefinition['priority'] ?? 0),
                            'status'       => $incomingStatus,
                            'confidence'   => $mappingDefinition['confidence'] ?? null,
                            'reviewed_at'  => $incomingStatus === 'confirmed' ? now() : null,
                        ]);
                        $mapping->save();
                    }
                    $mappings++;
                }

                foreach ($item['attributes'] ?? [] as $attribute) {
                    $platformAttribute = PlatformCategoryAttribute::updateOrCreate(
                        [
                            'platform_category_id' => $platformCategory->id,
                            'external_id'          => (string) $attribute['external_id'],
                        ],
                        [
                            'name'        => (string) $attribute['name'],
                            'type'        => $attribute['type'] ?? null,
                            'required'    => (bool) ($attribute['required'] ?? false),
                            'rules'       => $attribute['rules'] ?? null,
                            'raw_payload' => $attribute['raw_payload'] ?? null,
                        ]
                    );
                    $attributes++;
                    foreach ($attribute['canonical_mappings'] ?? [] as $attributeMapping) {
                        $categoryId = Category::where('code', $attributeMapping['category_code'] ?? null)->value('id');
                        if (! $categoryId) {
                            continue;
                        }
                        CategoryAttributeMapping::updateOrCreate(
                            [
                                'category_id'                    => $categoryId,
                                'platform_category_attribute_id' => $platformAttribute->id,
                            ],
                            [
                                'source_attribute_code' => (string) $attributeMapping['source_attribute_code'],
                                'status'                => $attributeMapping['status'] ?? 'active',
                                'transform'             => $attributeMapping['transform'] ?? null,
                            ]
                        );
                        $attributeMappings++;
                    }
                }
            }
            if (($definition['replace_categories'] ?? false) && $seenExternalIds !== []) {
                PlatformCategory::query()
                    ->where('platform_taxonomy_id', $taxonomy->id)
                    ->whereNotIn('external_id', $seenExternalIds)
                    ->delete();
            }
        }

        return compact('taxonomies', 'categories', 'mappings', 'attributes', 'attributeMappings');
    }

    protected function syncSourceMappings(array $definitions): array
    {
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        foreach ($definitions as $definition) {
            $category = Category::query()
                ->where('code', $definition['category_code'] ?? null)
                ->where('taxonomy_type', 'standard')
                ->first();
            $sourceCategory = Category::query()
                ->where('code', $definition['source_category_code'] ?? null)
                ->where('taxonomy_type', '!=', 'standard')
                ->first();
            if (! $category || ! $sourceCategory) {
                continue;
            }
            $mapping = CategorySourceMapping::firstOrNew([
                'category_id'        => $category->id,
                'source_category_id' => $sourceCategory->id,
            ]);
            $wasNew = ! $mapping->exists;
            $incomingStatus = $definition['status'] ?? 'draft';
            if (! ($mapping->exists && $mapping->status === 'confirmed' && $incomingStatus !== 'confirmed')) {
                $mapping->fill([
                    'source_platform' => $definition['source_platform'] ?? $sourceCategory->source_platform ?? 'unknown',
                    'mapping_type'    => $definition['mapping_type'] ?? 'exact',
                    'status'          => $incomingStatus,
                    'confidence'      => $definition['confidence'] ?? null,
                    'reviewed_at'     => $incomingStatus === 'confirmed' ? now() : null,
                ]);
            }
            $dirty = $mapping->isDirty();
            $mapping->save();
            if ($wasNew) {
                $created++;
            } elseif ($dirty) {
                $updated++;
            } else {
                $unchanged++;
            }
        }

        return compact('created', 'updated', 'unchanged');
    }

    protected function syncAssignments(array $definitions): int
    {
        $count = 0;
        foreach ($definitions as $definition) {
            $product = Product::where('sku', $definition['sku'] ?? null)->first();
            $category = Category::where('code', $definition['category_code'] ?? null)->first();
            if (! $product || ! $category || ! $category->is_assignable) {
                continue;
            }
            if (($definition['role'] ?? 'primary') === 'primary') {
                ProductCategoryAssignment::where('product_id', $product->id)
                    ->where('role', 'primary')
                    ->where('category_id', '!=', $category->id)
                    ->delete();
            }
            ProductCategoryAssignment::updateOrCreate(
                [
                    'product_id'  => $product->id,
                    'category_id' => $category->id,
                    'role'        => $definition['role'] ?? 'primary',
                ],
                [
                    'status'           => $definition['status'] ?? 'proposed',
                    'method'           => $definition['method'] ?? 'source',
                    'confidence'       => $definition['confidence'] ?? null,
                    'evidence'         => $definition['evidence'] ?? null,
                    'taxonomy_version' => $definition['taxonomy_version'] ?? null,
                ]
            );
            if (in_array(($definition['method'] ?? ''), ['rule', 'source'], true)) {
                $platformCategory = CategoryMapping::query()
                    ->with('platformCategory.taxonomy')
                    ->where('category_id', $category->id)
                    ->where('status', 'confirmed')
                    ->whereHas('platformCategory.taxonomy', fn ($query) => $query
                        ->where('platform', 'tiktok')
                        ->where('status', 'active'))
                    ->orderByDesc('confidence')
                    ->first()?->platformCategory;
                resolve(ProductCategoryClassificationManager::class)->remember($product, 'rule', [
                    'standard' => [
                        'value' => $category->code,
                        'path'  => $category->source_path ?: $category->name,
                    ],
                    'platform' => $platformCategory ? [
                        'value' => $platformCategory->external_id,
                        'path'  => $platformCategory->path,
                    ] : null,
                    'confidence' => $definition['confidence'] ?? null,
                    'reason'     => implode('；', (array) ($definition['evidence'] ?? [])),
                    'evidence'   => (array) ($definition['evidence'] ?? []),
                    'provider'   => 'product-pipeline-rules',
                ]);
            }
            $count++;
        }

        return $count;
    }

    protected function migrateSourceCategory(string $sourceCode, string $fallbackCode): int
    {
        $fallback = Category::where('code', $fallbackCode)->first();
        if (! $fallback) {
            return 0;
        }
        $count = 0;
        Product::query()->whereJsonContains('values->categories', $sourceCode)->chunkById(100, function ($products) use ($sourceCode, $fallback, &$count): void {
            foreach ($products as $product) {
                $values = $product->values ?: [];
                $categories = array_values(array_unique(array_map(
                    fn ($code) => $code === $sourceCode ? $fallback->code : $code,
                    $values['categories'] ?? []
                )));
                $values['categories'] = $categories;
                $product->values = $values;
                $product->save();
                ProductCategoryAssignment::updateOrCreate(
                    ['product_id' => $product->id, 'category_id' => $fallback->id, 'role' => 'primary'],
                    ['status' => 'proposed', 'method' => 'migration', 'confidence' => 0, 'evidence' => ['source category removed']]
                );
                $count++;
            }
        });

        return $count;
    }

    public function resolve(
        string $categoryCode,
        string $platform,
        ?string $region,
        ?string $sku = null
    ): ?array {
        if ($sku !== null) {
            $assignmentConfirmed = ProductCategoryAssignment::query()
                ->where('role', 'primary')
                ->where('status', 'confirmed')
                ->whereHas('product', fn ($query) => $query->where('sku', $sku))
                ->whereHas('category', fn ($query) => $query->where('code', $categoryCode))
                ->exists();

            if (! $assignmentConfirmed) {
                return null;
            }

            $productOverride = ProductPlatformCategoryAssignment::query()
                ->with('platformCategory.taxonomy')
                ->where('platform', strtolower($platform))
                ->where('status', 'confirmed')
                ->whereHas('product', fn ($query) => $query->where('sku', $sku))
                ->whereHas('platformCategory', fn ($query) => $query->where('enabled', true))
                ->whereHas('platformCategory.taxonomy', fn ($query) => $query->where('status', 'active'))
                ->first();

            if ($productOverride) {
                $platformCategory = $productOverride->platformCategory;

                return [
                    'canonical_code'   => $categoryCode,
                    'platform'         => $productOverride->platform,
                    'region'           => $region ? strtoupper($region) : $platformCategory->taxonomy->region,
                    'external_id'      => $platformCategory->external_id,
                    'path'             => $platformCategory->path,
                    'taxonomy_version' => $platformCategory->taxonomy->version,
                    'mapping_status'   => $productOverride->status,
                    'mapping_source'   => 'product_override',
                ];
            }
        }

        $mapping = CategoryMapping::query()
            ->with(['category', 'platformCategory.taxonomy'])
            ->whereHas('category', fn ($query) => $query->where('code', $categoryCode))
            ->where('status', 'confirmed')
            ->whereHas('platformCategory.taxonomy', function ($query) use ($platform, $region): void {
                $query->where('platform', $platform)->where('status', 'active');
                $region === null
                    ? $query->whereNull('region')
                    : $query->where('region', strtoupper($region));
            })
            ->whereHas('platformCategory', fn ($query) => $query->where('enabled', true))
            ->orderByDesc('priority')
            ->first();

        if (! $mapping) {
            return null;
        }

        return [
            'canonical_code'   => $mapping->category->code,
            'platform'         => $mapping->platformCategory->taxonomy->platform,
            'region'           => $mapping->platformCategory->taxonomy->region,
            'external_id'      => $mapping->platformCategory->external_id,
            'path'             => $mapping->platformCategory->path,
            'taxonomy_version' => $mapping->platformCategory->taxonomy->version,
            'mapping_status'   => $mapping->status,
            'mapping_source'   => 'standard_mapping',
        ];
    }

    protected function removeCategories(array $codes, string $fallbackCode): array
    {
        $codes = collect($codes)
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->reject(fn ($code) => in_array($code, ['root', $fallbackCode], true))
            ->values();
        $fallback = Category::query()
            ->where('code', $fallbackCode)
            ->where('status', 'active')
            ->where('is_assignable', true)
            ->firstOrFail();
        $targets = Category::query()->whereIn('code', $codes)->get();
        $targetIds = $targets->pluck('id');

        foreach ($targets as $target) {
            if ($target->children()->exists()) {
                throw new \RuntimeException("Cannot remove category with children: {$target->code}");
            }
            if (DB::table('channels')->where('root_category_id', $target->id)->exists()) {
                throw new \RuntimeException("Cannot remove channel root category: {$target->code}");
            }
        }

        $assignments = ProductCategoryAssignment::query()
            ->whereIn('category_id', $targetIds)
            ->get();
        foreach ($assignments as $assignment) {
            ProductCategoryAssignment::updateOrCreate(
                [
                    'product_id'  => $assignment->product_id,
                    'category_id' => $fallback->id,
                    'role'        => $assignment->role,
                ],
                [
                    'status'     => 'proposed',
                    'method'     => 'migration',
                    'confidence' => 0,
                    'evidence'   => ['removed legacy category' => $assignment->category_id],
                ]
            );
            $assignment->delete();
        }

        $updatedProducts = 0;
        $assignableCodes = Category::query()
            ->where('status', 'active')
            ->where('is_assignable', true)
            ->pluck('code')
            ->all();
        Product::query()->orderBy('id')->chunkById(200, function ($products) use (
            $codes,
            $fallback,
            $assignableCodes,
            &$updatedProducts
        ): void {
            foreach ($products as $product) {
                $values = $product->values ?: [];
                $existing = array_values(array_unique(array_map(
                    'strval',
                    is_array($values['categories'] ?? null) ? $values['categories'] : []
                )));
                if (array_intersect($existing, $codes->all()) === []) {
                    continue;
                }
                $remaining = array_values(array_intersect(
                    array_diff($existing, $codes->all()),
                    $assignableCodes
                ));
                if ($remaining === []) {
                    $remaining = [$fallback->code];
                }
                $values['categories'] = $remaining;
                $product->values = $values;
                $product->save();
                if (! ProductCategoryAssignment::query()
                    ->where('product_id', $product->id)
                    ->where('role', 'primary')
                    ->exists()) {
                    ProductCategoryAssignment::create([
                        'product_id'  => $product->id,
                        'category_id' => $fallback->id,
                        'role'        => 'primary',
                        'status'      => 'proposed',
                        'method'      => 'migration',
                        'confidence'  => 0,
                        'evidence'    => ['removed legacy category codes' => $codes->all()],
                    ]);
                }
                $updatedProducts++;
            }
        });

        $removed = [];
        foreach ($targets as $target) {
            $removed[] = $target->code;
            $target->delete();
        }
        if ($targets->isNotEmpty()) {
            Category::fixTree();
        }

        return [
            'requested'        => $codes->all(),
            'removed'          => $removed,
            'updated_products' => $updatedProducts,
        ];
    }
}
