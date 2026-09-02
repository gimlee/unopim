<x-admin::product.section-drawer
    id="taxonomy-assignment"
    title="商品类目归属"
    subtitle="逐级选择 PIM 标准类目与销售平台类目"
    icon="icon-folder"
>
    <x-slot:toggle>
        <x-admin::product.section-card
            id="taxonomy-assignment"
            title="商品类目归属"
            icon="icon-folder"
            summary="PIM / TikTok"
        />
    </x-slot:toggle>

    <x-slot:content>
        <v-product-taxonomy-panel></v-product-taxonomy-panel>
    </x-slot:content>
</x-admin::product.section-drawer>

@pushOnce('scripts')
    <script type="text/x-template" id="v-product-taxonomy-panel-template">
        <div class="grid gap-5" data-testid="product-taxonomy-panel">
            <div class="rounded border border-gray-200 p-4 dark:border-cherry-700">
                <x-category::taxonomy-cascader
                    component-id="product-standard-{{ $product->id }}"
                    type="standard"
                    input-name="standard_category_code"
                    :selected="$selectedStandardCategory"
                    label="PIM 标准类目"
                    form="product-taxonomy-detached"
                    required
                />
            </div>

            <div class="rounded border border-gray-200 p-4 dark:border-cherry-700">
                <x-category::taxonomy-cascader
                    component-id="product-platform-{{ $product->id }}"
                    type="platform"
                    input-name="platform_category_external_id"
                    :selected="$selectedPlatformCategory"
                    platform="tiktok"
                    region="MY"
                    label="TikTok 类目（各地区暂共用）"
                    form="product-taxonomy-detached"
                />
            </div>

            <div class="rounded border border-gray-200 p-4 dark:border-cherry-700">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white">AI 分类</h3>
                        <p class="mt-1 text-xs text-gray-500">只向模型发送当前商品摘要和最多 80 个候选类目；返回值必须属于候选集合。</p>
                    </div>
                    <a href="{{ route('admin.magic_ai.platform.index') }}" class="text-xs text-primary-600">Magic AI 配置</a>
                </div>

                <div v-if="platforms.length" class="flex flex-wrap items-center gap-2">
                    <select v-model="platformId" class="h-10 min-w-[190px] flex-1 rounded border border-gray-200 bg-white px-3 text-sm dark:border-cherry-700 dark:bg-cherry-800" aria-label="AI 平台">
                        <option v-for="item in platforms" :key="item.id" :value="item.id" v-text="`${item.label} (${item.provider})`"></option>
                    </select>
                    <select v-model="model" class="h-10 min-w-[170px] flex-1 rounded border border-gray-200 bg-white px-3 text-sm dark:border-cherry-700 dark:bg-cherry-800" aria-label="AI 模型">
                        <option v-for="item in models" :key="item" :value="item" v-text="item"></option>
                    </select>
                    <button type="button" class="secondary-button !h-10 !px-4" :disabled="classifying || ! model" @click="suggest">
                        <span v-text="classifying ? '分类中…' : 'AI 分类'"></span>
                    </button>
                </div>
                <p v-else class="text-xs text-orange-600">暂无可用于业务分类的 Magic AI 平台。请配置通用 API；Code Plan 不会用于商品数据。</p>

                <div v-if="suggestion" class="mt-3 rounded bg-blue-50 p-3 text-xs text-blue-800 dark:bg-cherry-800 dark:text-blue-200">
                    <p>AI 建议已代入上方选择器，请检查后保存。</p>
                    <p class="mt-1">置信度：@{{ Math.round(suggestion.confidence * 100) }}% · @{{ suggestion.reason || '无补充说明' }}</p>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="button" class="primary-button !h-10 !px-5" :disabled="saving" @click="save">
                    <span v-text="saving ? '保存中…' : '保存商品类目'"></span>
                </button>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-product-taxonomy-panel', {
            template: '#v-product-taxonomy-panel-template',

            data() {
                const platforms = @json($aiPlatforms);
                const preferred = platforms.find(item => item.is_default) || platforms[0] || null;

                return {
                    platforms,
                    platformId: preferred?.id || '',
                    model: preferred?.models?.[0] || '',
                    classifying: false,
                    saving: false,
                    suggestion: null,
                };
            },

            computed: {
                selectedPlatform() {
                    return this.platforms.find(item => item.id === Number(this.platformId)) || null;
                },
                models() {
                    return this.selectedPlatform?.models || [];
                },
            },

            watch: {
                platformId() {
                    this.model = this.models[0] || '';
                },
            },

            methods: {
                value(componentId) {
                    return document.querySelector(`[data-taxonomy-value="${componentId}"]`)?.value || '';
                },

                suggest() {
                    this.classifying = true;
                    this.$axios.post("{{ route('admin.catalog.products.taxonomy.ai-suggest', $product->id) }}", {
                        platform_id: this.platformId,
                        model: this.model,
                    }).then(response => {
                        this.suggestion = response.data.data;
                        this.$emitter.emit('taxonomy-cascader:set', {
                            componentId: 'product-standard-{{ $product->id }}',
                            value: this.suggestion.standard.value,
                        });
                        this.$emitter.emit('taxonomy-cascader:set', {
                            componentId: 'product-platform-{{ $product->id }}',
                            value: this.suggestion.platform.value,
                        });
                    }).catch(error => {
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message || 'AI 分类失败',
                        });
                    }).finally(() => this.classifying = false);
                },

                save() {
                    const standard = this.value('product-standard-{{ $product->id }}');
                    if (! standard) {
                        this.$emitter.emit('add-flash', { type: 'error', message: '请先选择 PIM 标准叶子类目。' });
                        return;
                    }

                    this.saving = true;
                    this.$axios.put("{{ route('admin.catalog.products.taxonomy.update', $product->id) }}", {
                        standard_category_code: standard,
                        platform: 'tiktok',
                        platform_category_external_id: this.value('product-platform-{{ $product->id }}') || null,
                        method: this.suggestion ? 'ai_reviewed' : 'manual',
                        confidence: this.suggestion?.confidence ?? null,
                        reason: this.suggestion?.reason ?? null,
                    }).then(response => {
                        this.suggestion = null;
                        this.$emitter.emit('taxonomy-standard-category-saved', {
                            code: response.data.standard_category_code,
                            label: response.data.standard_path,
                        });
                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                    }).catch(error => {
                        const errors = error.response?.data?.errors || {};
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: Object.values(errors).flat()[0] || error.response?.data?.message || '商品类目保存失败',
                        });
                    }).finally(() => this.saving = false);
                },
            },
        });
    </script>
@endPushOnce
