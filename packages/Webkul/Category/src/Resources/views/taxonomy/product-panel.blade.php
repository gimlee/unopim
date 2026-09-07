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

            <div class="grid gap-4 lg:grid-cols-2">
                <div
                    class="rounded border p-4"
                    :class="currentMethod === 'rule'
                        ? 'border-orange-400 bg-orange-50/60 dark:border-orange-500 dark:bg-orange-950/20'
                        : 'border-gray-200 dark:border-cherry-700'"
                >
                    <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white">规则分类</h3>
                            <span class="rounded bg-gray-100 px-2 py-0.5 text-[11px] text-gray-500 dark:bg-cherry-800 dark:text-gray-300">不推荐</span>
                        </div>
                        <span v-if="currentMethod === 'rule'" class="rounded bg-orange-500 px-2 py-0.5 text-[11px] font-semibold text-white">当前使用</span>
                    </div>
                    <div class="mb-3">
                        <p class="mt-1 text-xs text-gray-500">使用 1688 来源类目映射、类目别名和本地分类规则，不调用外部模型。</p>
                    </div>
                    <classification-result :result="caches.rule" empty-text="尚未执行规则分类"></classification-result>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" class="secondary-button !h-9 !px-3" :disabled="busy.rule" @click="classifyRule">
                            <span v-text="busy.rule ? '分类中…' : (caches.rule ? '重新分类' : '规则分类')"></span>
                        </button>
                        <button v-if="caches.rule" type="button" class="primary-button !h-9 !px-3" :disabled="applying" @click="apply('rule')">使用此结果</button>
                        <button v-if="caches.rule" type="button" class="transparent-button !h-9 !px-3" :disabled="busy.rule" @click="clearCache('rule')">清除缓存</button>
                    </div>
                </div>

                <div
                    class="rounded border p-4"
                    :class="currentMethod === 'ai'
                        ? 'border-primary-500 bg-primary-50/60 dark:border-primary-500 dark:bg-primary-950/20'
                        : 'border-gray-200 dark:border-cherry-700'"
                >
                    <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-gray-800 dark:text-white">AI 分类</h3>
                                <span class="rounded bg-primary-100 px-2 py-0.5 text-[11px] font-semibold text-primary-700 dark:bg-primary-900/40 dark:text-primary-300">默认推荐</span>
                                <span v-if="currentMethod === 'ai'" class="rounded bg-primary-600 px-2 py-0.5 text-[11px] font-semibold text-white">当前使用</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">模型只能从本地预筛选的候选类目中选择。</p>
                        </div>
                        <a href="{{ route('admin.magic_ai.platform.index') }}" class="text-xs text-primary-600">Magic AI 配置</a>
                    </div>
                    <div v-if="platforms.length" class="mb-3 flex flex-wrap items-center gap-2">
                        <select v-model="platformId" class="h-9 min-w-[160px] flex-1 rounded border border-gray-200 bg-white px-3 text-sm dark:border-cherry-700 dark:bg-cherry-800" aria-label="AI 平台">
                            <option v-for="item in platforms" :key="item.id" :value="item.id" v-text="`${item.label} (${item.provider})`"></option>
                        </select>
                        <select v-model="model" class="h-9 min-w-[145px] flex-1 rounded border border-gray-200 bg-white px-3 text-sm dark:border-cherry-700 dark:bg-cherry-800" aria-label="AI 模型">
                            <option v-for="item in models" :key="item" :value="item" v-text="item"></option>
                        </select>
                    </div>
                    <p v-else class="mb-3 text-xs text-orange-600">暂无已启用的 Magic AI 平台，请先完成平台配置。</p>
                    <classification-result :result="caches.ai" empty-text="尚未执行 AI 分类"></classification-result>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" class="secondary-button !h-9 !px-3" :disabled="busy.ai || ! model" @click="classifyAi">
                            <span v-text="busy.ai ? '分类中…' : (caches.ai ? '重新分类' : 'AI 分类')"></span>
                        </button>
                        <button v-if="caches.ai" type="button" class="primary-button !h-9 !px-3" :disabled="applying" @click="apply('ai')">使用此结果</button>
                        <button v-if="caches.ai" type="button" class="transparent-button !h-9 !px-3" :disabled="busy.ai" @click="clearCache('ai')">清除缓存</button>
                    </div>
                </div>
            </div>

            <div class="rounded bg-gray-50 px-4 py-3 text-xs text-gray-600 dark:bg-cherry-800 dark:text-gray-300">
                当前使用：<span class="font-semibold" v-text="methodLabel(currentMethod)"></span>。规则与 AI 结果分别缓存；缓存存在不代表正在使用，清除缓存也不会删除当前已应用类目。
            </div>

            <div class="flex justify-end">
                <button type="button" class="primary-button !h-10 !px-5" :disabled="saving" @click="save">
                    <span v-text="saving ? '保存中…' : '保存商品类目'"></span>
                </button>
            </div>
        </div>
    </script>

    <script type="text/x-template" id="v-product-classification-result-template">
        <div class="min-h-[90px] rounded bg-gray-50 p-3 text-xs dark:bg-cherry-800">
            <p v-if="! result" class="text-gray-500" v-text="emptyText"></p>
            <template v-else>
                <p class="font-medium text-gray-800 dark:text-white" v-text="result.standard?.path || '未返回 PIM 类目'"></p>
                <p class="mt-1 text-gray-600 dark:text-gray-300" v-text="result.platform?.path ? `TikTok：${result.platform.path}` : 'TikTok：没有已确认的平台映射'"></p>
                <p class="mt-1 text-gray-500">置信度：@{{ Math.round((result.confidence || 0) * 100) }}% · @{{ result.reason || '无补充说明' }}</p>
                <p class="mt-1 text-gray-400" v-text="result.generated_at ? `缓存时间：${new Date(result.generated_at).toLocaleString()}` : ''"></p>
            </template>
        </div>
    </script>

    <script type="module">
        app.component('classification-result', {
            template: '#v-product-classification-result-template',
            props: { result: Object, emptyText: String },
        });

        app.component('v-product-taxonomy-panel', {
            template: '#v-product-taxonomy-panel-template',

            data() {
                const platforms = @json($aiPlatforms);
                const preferred = platforms.find(item => item.is_default) || platforms[0] || null;

                return {
                    platforms,
                    platformId: preferred?.id || '',
                    model: preferred?.models?.[0] || '',
                    caches: @json($classificationCaches),
                    currentMethod: @json($currentClassificationMethod),
                    busy: { rule: false, ai: false },
                    applying: false,
                    saving: false,
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

                methodLabel(method) {
                    return { rule: '规则分类', ai: 'AI 分类', manual: '人工分类', unclassified: '未分类' }[method] || '未分类';
                },

                setResult(method, result) {
                    this.caches[method] = result;
                    this.$emitter.emit('taxonomy-cascader:set', {
                        componentId: 'product-standard-{{ $product->id }}',
                        value: result.standard?.value || '',
                    });
                    this.$emitter.emit('taxonomy-cascader:set', {
                        componentId: 'product-platform-{{ $product->id }}',
                        value: result.platform?.value || '',
                    });
                },

                classifyRule() {
                    this.busy.rule = true;
                    this.$axios.post("{{ route('admin.catalog.products.taxonomy.rule-suggest', $product->id) }}")
                        .then(response => this.setResult('rule', response.data.data))
                        .catch(error => this.flashError(error, '规则分类失败'))
                        .finally(() => this.busy.rule = false);
                },

                classifyAi() {
                    this.busy.ai = true;
                    this.$axios.post("{{ route('admin.catalog.products.taxonomy.ai-suggest', $product->id) }}", {
                        platform_id: this.platformId,
                        model: this.model,
                    }).then(response => {
                        this.setResult('ai', response.data.data);
                    }).catch(error => this.flashError(error, 'AI 分类失败'))
                        .finally(() => this.busy.ai = false);
                },

                apply(method) {
                    this.applying = true;
                    const url = @json(route('admin.catalog.products.taxonomy.classification.apply', ['productId' => $product->id, 'method' => '__METHOD__'])).replace('__METHOD__', method);
                    this.$axios.post(url).then(response => {
                        this.setResult(method, response.data.data);
                        this.currentMethod = method;
                        this.$emitter.emit('taxonomy-standard-category-saved', {
                            code: response.data.data.standard?.value,
                            label: response.data.data.standard?.path,
                        });
                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                    }).catch(error => this.flashError(error, '分类结果应用失败'))
                        .finally(() => this.applying = false);
                },

                clearCache(method) {
                    this.busy[method] = true;
                    const url = @json(route('admin.catalog.products.taxonomy.classification.clear', ['productId' => $product->id, 'method' => '__METHOD__'])).replace('__METHOD__', method);
                    this.$axios.delete(url).then(response => {
                        this.caches[method] = null;
                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                    }).catch(error => this.flashError(error, '清除分类缓存失败'))
                        .finally(() => this.busy[method] = false);
                },

                flashError(error, fallback) {
                    const errors = error.response?.data?.errors || {};
                    this.$emitter.emit('add-flash', {
                        type: 'error',
                        message: Object.values(errors).flat()[0] || error.response?.data?.message || fallback,
                    });
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
                        method: 'manual',
                        confidence: 1,
                        reason: '管理员手动选择',
                    }).then(response => {
                        this.currentMethod = 'manual';
                        this.$emitter.emit('taxonomy-standard-category-saved', {
                            code: response.data.standard_category_code,
                            label: response.data.standard_path,
                        });
                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                    }).catch(error => this.flashError(error, '商品类目保存失败'))
                        .finally(() => this.saving = false);
                },
            },
        });
    </script>
@endPushOnce
