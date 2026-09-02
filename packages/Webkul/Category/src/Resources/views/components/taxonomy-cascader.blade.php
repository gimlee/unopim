@props([
    'componentId',
    'type' => 'standard',
    'inputName',
    'selected' => '',
    'platform' => 'tiktok',
    'region' => 'MY',
    'label' => '',
    'required' => false,
    'form' => null,
    'lazy' => false,
])

<v-taxonomy-cascader
    component-id="{{ $componentId }}"
    type="{{ $type }}"
    input-name="{{ $inputName }}"
    selected-value="{{ $selected }}"
    platform="{{ $platform }}"
    region="{{ $region }}"
    label="{{ $label }}"
    :required="{{ $required ? 'true' : 'false' }}"
    :lazy="{{ $lazy ? 'true' : 'false' }}"
    form-id="{{ $form }}"
></v-taxonomy-cascader>

@pushOnce('scripts')
    <script type="text/x-template" id="v-taxonomy-cascader-template">
        <div class="grid gap-2" :data-taxonomy-cascader="componentId">
            <label v-if="label" class="text-sm font-medium text-gray-700 dark:text-gray-200" v-text="label"></label>

            <div class="flex flex-wrap items-center gap-2">
                <select
                    v-for="(level, index) in levels"
                    :key="`${componentId}-${index}`"
                    class="h-10 min-w-[150px] flex-1 rounded border border-gray-200 bg-white px-3 text-sm dark:border-cherry-700 dark:bg-cherry-800"
                    :value="level.selected_id || ''"
                    :aria-label="`${label || '类目'} 第 ${index + 1} 级`"
                    @change="selectLevel(index, $event.target.value)"
                >
                    <option value="">请选择第 @{{ index + 1 }} 级</option>
                    <option
                        v-for="option in level.options"
                        :key="option.id"
                        :value="option.id"
                        :disabled="option.is_leaf && ! option.enabled"
                        v-text="option.label + (option.is_leaf && ! option.enabled ? '（不可用）' : '')"
                    ></option>
                </select>

                <span v-if="loading" class="text-xs text-gray-400">加载中…</span>
            </div>

            <p v-if="selection" class="text-xs text-gray-500 dark:text-gray-300">
                已选择：<span v-text="selection.path"></span>
            </p>
            <p v-else-if="error" class="text-xs text-red-600" v-text="error"></p>

            <input
                type="hidden"
                :name="inputName"
                :value="selection?.value || ''"
                :required="required"
                :form="formId || undefined"
                :data-taxonomy-value="componentId"
            >
            <template v-if="type === 'platform'">
                <input type="hidden" name="name" :value="selection?.label || ''" :form="formId || undefined" :data-taxonomy-meta="`${componentId}:name`">
                <input type="hidden" name="path" :value="selection?.path || ''" :form="formId || undefined" :data-taxonomy-meta="`${componentId}:path`">
                <input type="hidden" name="version" :value="selection?.version || taxonomy?.version || 'current'" :form="formId || undefined" :data-taxonomy-meta="`${componentId}:version`">
                <input type="hidden" name="platform" :value="selection?.platform || taxonomy?.platform || platform" :form="formId || undefined" :data-taxonomy-meta="`${componentId}:platform`">
                <input type="hidden" name="region" :value="selection?.region || taxonomy?.region || region" :form="formId || undefined" :data-taxonomy-meta="`${componentId}:region`">
            </template>
        </div>
    </script>

    <script type="module">
        app.component('v-taxonomy-cascader', {
            template: '#v-taxonomy-cascader-template',

            props: {
                componentId: String,
                type: String,
                inputName: String,
                selectedValue: { type: String, default: '' },
                platform: { type: String, default: 'tiktok' },
                region: { type: String, default: 'MY' },
                label: { type: String, default: '' },
                required: { type: Boolean, default: false },
                formId: { type: String, default: '' },
                lazy: { type: Boolean, default: false },
            },

            data() {
                return {
                    levels: [],
                    selection: null,
                    taxonomy: null,
                    loading: false,
                    error: '',
                };
            },

            mounted() {
                if (! this.lazy) {
                    this.loadInitial(this.selectedValue);
                }
                this.$emitter.on('taxonomy-cascader:set', this.handleExternalSelection);
                window.addEventListener('taxonomy-cascader:open', this.handleOpen);
            },

            beforeUnmount() {
                this.$emitter.off('taxonomy-cascader:set', this.handleExternalSelection);
                window.removeEventListener('taxonomy-cascader:open', this.handleOpen);
            },

            methods: {
                request(params = {}) {
                    return this.$axios.get("{{ route('admin.catalog.taxonomy.selector.options') }}", {
                        params: {
                            type: this.type,
                            platform: this.platform,
                            region: this.region,
                            ...params,
                        },
                    });
                },

                loadInitial(selected = '') {
                    this.loading = true;
                    this.error = '';
                    this.request(selected ? { selected } : {})
                        .then(response => {
                            this.levels = response.data.levels || [{ options: response.data.options || [], selected_id: null }];
                            this.selection = response.data.selection || null;
                            this.taxonomy = response.data.taxonomy || null;
                        })
                        .catch(error => {
                            this.error = error.response?.data?.message || '类目加载失败';
                        })
                        .finally(() => this.loading = false);
                },

                selectLevel(index, id) {
                    this.levels = this.levels.slice(0, index + 1);
                    this.levels[index].selected_id = id ? Number(id) : null;
                    this.selection = null;

                    if (! id) {
                        return;
                    }

                    const option = this.levels[index].options.find(item => item.id === Number(id));
                    if (! option) {
                        return;
                    }

                    if (option.is_leaf) {
                        if (option.enabled) {
                            this.selection = option;
                        }
                        return;
                    }

                    this.loading = true;
                    this.request({ parent_id: option.id })
                        .then(response => {
                            this.taxonomy = response.data.taxonomy || this.taxonomy;
                            this.levels.push({ options: response.data.options || [], selected_id: null });
                        })
                        .catch(error => {
                            this.error = error.response?.data?.message || '下级类目加载失败';
                        })
                        .finally(() => this.loading = false);
                },

                handleExternalSelection(payload) {
                    if (payload?.componentId !== this.componentId || ! payload.value) {
                        return;
                    }

                    this.loadInitial(payload.value);
                },

                handleOpen(event) {
                    if (! event.detail?.componentIds?.includes(this.componentId) || this.levels.length) {
                        return;
                    }

                    this.loadInitial(this.selectedValue);
                },
            },
        });
    </script>
@endPushOnce
