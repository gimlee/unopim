<v-product-short-description-ai
    product-id="{{ $productId }}"
    channel-code="{{ $channelCode }}"
    locale-code="{{ $localeCode }}"
></v-product-short-description-ai>

@pushOnce('scripts')
    <script type="text/x-template" id="v-product-short-description-ai-template">
        <div class="inline-flex gap-1 items-center">
            <button
                type="button"
                class="secondary-button !h-7 !px-2.5"
                data-no-toggle
                :disabled="optimizing"
                @click="openTemplates"
            >
                描述模板
            </button>

            <button
                type="button"
                class="primary-button !h-7 !px-2.5 inline-flex items-center gap-1.5"
                data-no-toggle
                :disabled="optimizing"
                @click="optimizeFromButton"
            >
                <svg v-if="optimizing" class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span v-text="optimizing ? 'AI生成中…' : 'AI商品描述'"></span>
            </button>

            <x-admin::modal ref="templatesModal">
                <x-slot:header>
                    <div>
                        <p class="text-lg font-semibold text-gray-800 dark:text-white">商品描述优化模板</p>
                        <p class="mt-1 text-sm text-gray-500">选择模板后可以查看或修改内容，再使用该模板优化 Short Description。</p>
                    </div>
                </x-slot:header>

                <x-slot:content>
                    <div v-if="loading" class="py-10 text-center text-sm text-gray-500">正在加载模板…</div>

                    <div v-else-if="! templates.length" class="rounded border border-dashed p-8 text-center text-sm text-gray-500">
                        暂无商品描述模板，请在 Magic AI → System Prompts 中新增“商品描述优化”用途的提示词。
                    </div>

                    <div v-else class="grid grid-cols-1 gap-4 lg:grid-cols-3" style="min-height: 420px">
                        <div class="space-y-2 border-r pr-3 dark:border-cherry-700 lg:col-span-1">
                            <button
                                v-for="item in templates"
                                :key="item.id"
                                type="button"
                                class="w-full rounded border px-3 py-2.5 text-left transition"
                                :class="selected?.id === item.id
                                    ? 'border-primary-500 bg-primary-50 dark:bg-cherry-800'
                                    : 'border-gray-200 hover:border-primary-300 dark:border-cherry-700'"
                                @click="select(item)"
                            >
                                <span class="block text-sm font-semibold text-gray-800 dark:text-white" v-text="item.title"></span>
                                <span v-if="item.is_default" class="mt-1 inline-block rounded bg-green-100 px-1.5 py-0.5 text-[11px] text-green-700">当前默认</span>
                            </button>
                        </div>

                        <div v-if="selected" class="flex min-w-0 flex-col gap-3 lg:col-span-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">模板名称</label>
                                <input v-model="draftTitle" type="text" class="w-full rounded border border-gray-200 px-3 py-2.5 text-sm dark:border-cherry-700 dark:bg-cherry-800 dark:text-white" />
                            </div>

                            <div class="flex-1">
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">提示词内容</label>
                                <textarea v-model="draftContent" class="w-full resize-y rounded border border-gray-200 px-3 py-2.5 text-sm leading-6 dark:border-cherry-700 dark:bg-cherry-800 dark:text-white" style="height: 300px"></textarea>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs text-gray-500">模板修改会同步保存到 System Prompts。</p>
                                <button type="button" class="secondary-button !h-9" :disabled="savingTemplate" @click="saveTemplate">
                                    <span v-text="savingTemplate ? '保存中…' : '保存模板修改'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </x-slot:content>

                <x-slot:footer>
                    <div class="flex w-full items-center justify-between gap-3">
                        <p class="truncate text-sm text-gray-500" v-text="selected ? `本次使用：${selected.title}` : '请先选择模板'"></p>
                        <button type="button" class="primary-button" :disabled="optimizing || ! selected" @click="optimize">
                            <span v-text="optimizing ? 'AI 商品描述生成中…' : '使用此模板生成 AI 商品描述'"></span>
                        </button>
                    </div>
                </x-slot:footer>
            </x-admin::modal>
        </div>
    </script>

    <script type="module">
        app.component('v-product-short-description-ai', {
            template: '#v-product-short-description-ai-template',

            props: ['productId', 'channelCode', 'localeCode'],

            data() {
                return {
                    templates: [],
                    selected: null,
                    draftTitle: '',
                    draftContent: '',
                    loading: false,
                    optimizing: false,
                    savingTemplate: false,
                    loaded: false,
                };
            },

            mounted() {
                this.$emitter?.on('product-description-ai:templates', this.openTemplates);
                this.$emitter?.on('product-description-ai:optimize', this.optimizeFromButton);
                this.$emitter?.on('product-description-ai:set-optimizing', (val) => {
                    this.optimizing = Boolean(val);
                });
                this.$emitter?.on('product-description-ai:apply', (val) => {
                    if (val) this.applyShortDescription(val);
                });
            },

            beforeUnmount() {
                this.$emitter?.off('product-description-ai:templates', this.openTemplates);
                this.$emitter?.off('product-description-ai:optimize', this.optimizeFromButton);
                this.$emitter?.off('product-description-ai:set-optimizing');
                this.$emitter?.off('product-description-ai:apply');
            },

            methods: {
                emitFlash(type, message) {
                    if (this.$emitter) {
                        this.$emitter.emit('add-flash', { type, message });
                    }
                    window.dispatchEvent(new CustomEvent('unopim:flash', { detail: { type, message } }));
                },

                loadTemplates() {
                    if (this.loading) return Promise.resolve();

                    this.loading = true;

                    return this.$axios.get(@json(route('admin.catalog.products.description_ai.templates')))
                        .then(response => {
                            this.templates = response.data.data || [];
                            const selectedId = this.selected?.id;
                            this.select(this.templates.find(item => item.id === selectedId)
                                || this.templates.find(item => item.is_default)
                                || this.templates[0]
                                || null);
                            this.loaded = true;
                        })
                        .finally(() => this.loading = false);
                },

                select(item) {
                    this.selected = item;
                    this.draftTitle = item?.title || '';
                    this.draftContent = item?.content || '';
                },

                openTemplates() {
                    this.loadTemplates()
                        .then(() => this.$refs.templatesModal.toggle())
                        .catch(this.notifyTemplateLoadError);
                },

                optimizeFromButton() {
                    if (this.optimizing) return;

                    const ready = this.loaded ? Promise.resolve() : this.loadTemplates();
                    ready
                        .then(() => {
                            if (! this.selected) {
                                this.emitFlash('warning', '请先创建商品描述优化模板。');
                                return;
                            }

                            this.optimize();
                        })
                        .catch(this.notifyTemplateLoadError);
                },

                notifyTemplateLoadError(error) {
                    this.emitFlash('error', error.response?.data?.message || '商品描述模板加载失败。');
                },

                readField(code) {
                    const editor = window.tinymce?.get(code)
                        || (window.tinymce?.editors || []).find(e => e.id && e.id.includes(code));
                    if (editor) return editor.getContent();

                    const field = document.getElementById(code)
                        || document.querySelector(`[name*="[${code}]"]`)
                        || document.querySelector(`textarea[name$="[${code}]"]`)
                        || document.querySelector(`input[name$="[${code}]"]`);
                    return field ? field.value : '';
                },

                applyShortDescription(value) {
                    const editor = window.tinymce?.get('short_description')
                        || (window.tinymce?.editors || []).find(e => e.id && e.id.includes('short_description'));

                    if (editor) {
                        editor.setContent(value);
                    }

                    const field = document.getElementById('short_description')
                        || document.querySelector('[name*="[short_description]"]')
                        || document.querySelector('textarea[name$="[short_description]"]');

                    if (field) {
                        field.value = value;
                        field.dispatchEvent(new Event('input', { bubbles: true }));
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                },

                optimize() {
                    if (! this.selected || this.optimizing) return;

                    this.optimizing = true;
                    const url = @json(route('admin.catalog.products.description_ai.optimize', '__ID__')).replace('__ID__', this.productId);

                    this.$axios.post(url, {
                        channel: this.channelCode,
                        locale: this.localeCode,
                        template_id: this.selected.id,
                        source_name: this.readField('name'),
                        source_short_description: this.readField('short_description'),
                        source_description: this.readField('description'),
                    }).then(response => {
                        const result = response.data.data;
                        this.applyShortDescription(result.short_description);
                        this.$emitter?.emit('content-policy:revision-created', result);
                        this.emitFlash('success', response.data.message || 'AI 商品描述生成成功！');
                    }).catch(error => {
                        this.emitFlash('error', error.response?.data?.message || 'AI 商品描述生成失败。');
                    }).finally(() => this.optimizing = false);
                },

                saveTemplate() {
                    if (! this.selected || this.savingTemplate) return;

                    this.savingTemplate = true;
                    const url = @json(route('admin.catalog.products.description_ai.templates.update', '__ID__')).replace('__ID__', this.selected.id);

                    this.$axios.put(url, { title: this.draftTitle, content: this.draftContent })
                        .then(response => {
                            this.selected.title = this.draftTitle;
                            this.selected.content = this.draftContent;
                            this.emitFlash('success', response.data.message || '模板保存成功！');
                        }).catch(error => {
                            const errors = error.response?.data?.errors;
                            const message = errors ? Object.values(errors).flat()[0] : error.response?.data?.message;
                            this.emitFlash('error', message || '模板保存失败。');
                        }).finally(() => this.savingTemplate = false);
                },
            },
        });
    </script>
@endPushOnce
