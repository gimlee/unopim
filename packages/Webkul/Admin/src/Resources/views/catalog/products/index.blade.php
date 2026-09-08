<x-admin::layouts>
    <x-admin::products.bulk-edit-modal />
    <x-slot:title>
        @lang('admin::app.catalog.products.index.title')
        </x-slot>

        <x-admin::page-header :title="trans('admin::app.catalog.products.index.title')">
            <x-slot:actions>
                <!-- Export Modal -->
                @if (bouncer()->hasPermission('catalog.products.quick_export'))
                <x-admin::datagrid.export src="{{ route('admin.catalog.products.quick-export') }}" />
                @endif

                {!! view_render_event('unopim.admin.catalog.products.create.before') !!}

                @if (bouncer()->hasPermission('catalog.products.create'))
                <v-create-product-form>
                    <button
                        type="button"
                        class="primary-button">
                        @lang('admin::app.catalog.products.index.create-btn')
                    </button>
                </v-create-product-form>
                @endif

                {!! view_render_event('unopim.admin.catalog.products.create.after') !!}
            </x-slot>
        </x-admin::page-header>

        {!! view_render_event('unopim.admin.catalog.products.list.before') !!}

    <!-- Datagrid -->
    <x-admin::datagrid
        src="{{ route('admin.catalog.products.index') }}"
        filter-attributes-src="{{ route('admin.catalog.products.filterable_attributes') }}"
        views-src="{{ route('admin.catalog.products.grid_views.index') }}"
        scope-channel="{{ core()->getRequestedChannelCode() }}"
        scope-locale="{{ core()->getRequestedLocaleCode() }}"
        :isMultiRow="true"
    >
        <template #body="{ columns, actions, massActions, records, meta, performAction, handleRowClick, applied, isLoading }">
            <v-product-list-body
                :columns="columns"
                :actions="actions"
                :mass-actions="massActions"
                :records="records"
                :meta="meta"
                :perform-action="performAction"
                :handle-row-click="handleRowClick"
                :applied="applied"
                :is-loading="isLoading"
            ></v-product-list-body>
        </template>
    </x-admin::datagrid>

        {!! view_render_event('unopim.admin.catalog.products.list.after') !!}

        @pushOnce('scripts')
        <script type="text/x-template" id="v-product-list-body-template">
            <div class="w-full">
                <template v-if="isLoading">
                    <x-admin::shimmer.datagrid.table.body :isMultiRow="true" />
                </template>

                <template v-else-if="records.length">
                    <template v-for="record in records" :key="record[meta.primary_column]">
                        <div
                            class="row grid gap-2.5 items-center px-4 py-4 border-b dark:border-cherry-800 text-gray-600 dark:text-gray-300 transition-all cursor-pointer hover:bg-primary-50 hover:bg-opacity-30 dark:hover:bg-cherry-800"
                            :style="`grid-template-columns: ${gridTemplateColumns}`"
                            @click="handleRowClick($event, record)"
                        >
                            <p v-if="massActions.length" @click.stop>
                                <label :for="`mass_action_select_record_${record[meta.primary_column]}`">
                                    <input
                                        class="peer hidden"
                                        type="checkbox"
                                        :id="`mass_action_select_record_${record[meta.primary_column]}`"
                                        :value="record[meta.primary_column]"
                                        v-model="applied.massActions.indices"
                                    >
                                    <span class="icon-checkbox-normal peer-checked:icon-checkbox-check peer-checked:text-primary-700 cursor-pointer rounded-md text-2xl"></span>
                                </label>
                            </p>

                            <div class="min-w-0" v-for="column in visibleColumns" :key="column.index">
                                <div v-if="column.index === 'sku'" class="flex min-w-0 items-center gap-2" @click.stop>
                                    <button
                                        type="button"
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded border border-gray-200 text-base text-gray-500 hover:border-primary-500 hover:text-primary-600 dark:border-cherry-700"
                                        :title="expanded[record.product_id] ? '收起 Variations' : '展开 Variations'"
                                        :aria-expanded="expanded[record.product_id] ? 'true' : 'false'"
                                        @click="toggle(record)"
                                    >
                                        <span :class="loading[record.product_id] ? 'icon-loader animate-spin' : (expanded[record.product_id] ? 'icon-chevron-up' : 'icon-chevron-down')"></span>
                                    </button>
                                    <span class="truncate text-xs font-medium tabular-nums tracking-tight" :title="stripHtml(record[column.index])" v-text="record[column.index]"></span>
                                    <button
                                        type="button"
                                        class="shrink-0 rounded p-1 text-lg text-gray-400 transition hover:bg-primary-100 hover:text-primary-600 dark:hover:bg-cherry-800"
                                        :class="copiedSku === record[column.index] ? 'icon-done !text-green-600' : 'icon-copy'"
                                        :title="copiedSku === record[column.index] ? '已复制' : '复制 SKU'"
                                        :aria-label="`复制 SKU ${record[column.index]}`"
                                        @click.stop="copySku(record[column.index])"
                                    ></button>
                                </div>

                                <img
                                    v-else-if="column.type === 'image'"
                                    :src="record[column.index] || '{{ unopim_asset('images/placeholder.svg') }}'"
                                    alt="@lang('admin::app.components.datagrid.table.thumbnail')"
                                    class="h-[60px] w-[60px] rounded-lg border border-gray-300 object-cover shadow-sm"
                                >

                                <template v-else-if="column.type === 'gallery'">
                                    <video v-if="record[column.index]?.type === 'video'" :src="record[column.index].url" class="h-[60px] w-[60px] rounded-lg border border-gray-300 object-cover shadow-sm"></video>
                                    <img v-else :src="record[column.index]?.url || '{{ unopim_asset('images/placeholder.svg') }}'" class="h-[60px] w-[60px] rounded-lg border border-gray-300 object-cover shadow-sm" alt="">
                                </template>

                                <p v-else-if="column.closure" class="truncate" :title="stripHtml(record[column.index])" v-html="record[column.index]"></p>
                                <p v-else class="truncate" :title="stripHtml(record[column.index])" v-text="record[column.index]"></p>
                            </div>

                            <div class="flex items-center justify-end gap-0.5 select-none" @click.stop>
                                <span
                                    v-for="(action, actionIndex) in record.actions"
                                    :key="actionIndex"
                                    class="cursor-pointer rounded p-1 text-base transition-all hover:bg-primary-100 dark:hover:bg-gray-800"
                                    :class="action.icon"
                                    :title="action.title || ''"
                                    v-text="action.icon ? '' : action.title"
                                    @click="performAction(action, record)"
                                ></span>
                            </div>
                        </div>

                        <div v-if="expanded[record.product_id]" class="border-b border-primary-100 bg-primary-50/40 px-5 py-4 dark:border-cherry-800 dark:bg-cherry-950/30">
                            <div class="mb-3 flex items-center justify-between">
                                <div>
                                    <span class="font-semibold text-gray-800 dark:text-white">Variations / SKU</span>
                                    <span class="ml-2 text-xs text-gray-500" v-text="`${variationTotals[record.product_id] || 0} 个`"></span>
                                </div>
                                <span class="text-xs text-gray-500">Simple 商品不再作为顶层商品重复显示</span>
                            </div>

                            <div v-if="errors[record.product_id]" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700" v-text="errors[record.product_id]"></div>
                            <div v-else-if="loading[record.product_id]" class="py-5 text-center text-sm text-gray-500">正在加载 SKU…</div>
                            <div v-else-if="!(variations[record.product_id] || []).length" class="rounded border border-dashed border-gray-300 py-5 text-center text-sm text-gray-500">该商品还没有 Simple SKU</div>

                            <div v-else class="overflow-x-auto rounded border border-gray-200 bg-white dark:border-cherry-700 dark:bg-cherry-900">
                                <table class="w-full min-w-[980px] text-sm">
                                    <thead class="bg-gray-50 text-left text-xs text-gray-500 dark:bg-cherry-800">
                                        <tr><th class="px-3 py-2">图片</th><th>SKU / 商品名称</th><th>规格</th><th>价格</th><th>库存</th><th>状态</th><th>更新时间</th><th class="pr-3 text-right">操作</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="variation in variations[record.product_id]" :key="variation.id" class="border-t dark:border-cherry-800">
                                            <td class="px-3 py-2"><img :src="variation.image || '{{ unopim_asset('images/placeholder.svg') }}'" class="h-11 w-11 rounded border object-cover" alt=""></td>
                                            <td class="max-w-[300px] pr-3">
                                                <div class="flex min-w-0 items-center gap-1.5">
                                                    <a class="truncate font-medium text-blue-600" :href="variation.redirect_url" v-text="variation.sku"></a>
                                                    <button
                                                        type="button"
                                                        class="shrink-0 rounded p-1 text-base text-gray-400 transition hover:bg-primary-100 hover:text-primary-600 dark:hover:bg-cherry-800"
                                                        :class="copiedSku === variation.sku ? 'icon-done !text-green-600' : 'icon-copy'"
                                                        :title="copiedSku === variation.sku ? '已复制' : '复制 SKU'"
                                                        :aria-label="`复制 SKU ${variation.sku}`"
                                                        @click.stop="copySku(variation.sku)"
                                                    ></button>
                                                </div>
                                                <div class="mt-0.5 truncate text-xs text-gray-500" :title="variation.name" v-text="variation.name"></div>
                                                <div v-if="variation.image_inherited" class="mt-0.5 text-[11px] text-primary-600">图片继承自父商品主图</div>
                                            </td>
                                            <td class="max-w-[260px] pr-3"><span v-for="(value, code) in variation.options" :key="code" class="mr-1.5 inline-flex rounded bg-gray-100 px-2 py-1 text-xs dark:bg-cherry-800"><span class="text-gray-400" v-text="`${code}: `"></span><span v-text="value"></span></span><span v-if="!Object.keys(variation.options || {}).length">-</span></td>
                                            <td class="pr-3" v-text="variation.price"></td>
                                            <td class="pr-3" v-text="variation.stock"></td>
                                            <td class="pr-3"><span :class="variation.status ? 'label-active' : 'label-info'" v-text="variation.status ? '启用' : '禁用'"></span></td>
                                            <td class="pr-3 text-xs text-gray-500" v-text="variation.updated_at || '-' "></td>
                                            <td class="pr-3 text-right"><a class="text-blue-600" :href="variation.redirect_url">编辑</a></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </template>

                <div v-else class="row grid gap-2 px-4 py-8 border-b dark:border-cherry-800 text-center text-gray-500">
                    没有可显示的 Configurable 商品
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-product-list-body', {
                template: '#v-product-list-body-template',

                props: ['columns', 'actions', 'massActions', 'records', 'meta', 'performAction', 'handleRowClick', 'applied', 'isLoading'],

                data() {
                    return {
                        expanded: {},
                        loading: {},
                        variations: {},
                        variationTotals: {},
                        errors: {},
                        copiedSku: '',
                    };
                },

                computed: {
                    visibleColumns() {
                        return (this.columns || []).filter(column => column.visible !== false);
                    },

                    gridsCount() {
                        return this.visibleColumns.length + (this.actions.length ? 1 : 0) + (this.massActions.length ? 1 : 0);
                    },

                    gridTemplateColumns() {
                        const tracks = this.visibleColumns.map(column => {
                            if (column.index === 'sku') return 'minmax(155px, 1.15fr)';
                            if (column.type === 'image' || column.type === 'gallery') return '72px';
                            if (column.index === 'name') return 'minmax(160px, 1.35fr)';
                            if (column.index === 'created_at' || column.index === 'updated_at') return 'minmax(120px, 0.9fr)';

                            return 'minmax(80px, 1fr)';
                        });

                        if (this.massActions.length) {
                            tracks.unshift('36px');
                        }

                        if (this.actions.length) {
                            tracks.push('80px');
                        }

                        return tracks.join(' ');
                    },
                },

                methods: {
                    async copySku(sku) {
                        const text = String(sku || '').trim();

                        if (!text) {
                            return;
                        }

                        try {
                            if (navigator.clipboard && window.isSecureContext) {
                                await navigator.clipboard.writeText(text);
                            } else {
                                const input = document.createElement('textarea');
                                input.value = text;
                                input.setAttribute('readonly', '');
                                input.style.position = 'fixed';
                                input.style.opacity = '0';
                                document.body.appendChild(input);
                                input.select();
                                document.execCommand('copy');
                                input.remove();
                            }

                            this.copiedSku = text;
                            window.setTimeout(() => {
                                if (this.copiedSku === text) {
                                    this.copiedSku = '';
                                }
                            }, 1500);
                        } catch (error) {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: 'SKU 复制失败，请手动复制',
                            });
                        }
                    },

                    async toggle(record) {
                        const id = record.product_id;
                        this.expanded[id] = !this.expanded[id];

                        if (!this.expanded[id] || this.variations[id] || this.loading[id]) {
                            return;
                        }

                        this.loading[id] = true;
                        this.errors[id] = '';

                        try {
                            const route = @json(route('admin.catalog.products.variations', ['configurableId' => '__PRODUCT_ID__']));
                            const response = await this.$axios.get(route.replace('__PRODUCT_ID__', id));
                            this.variations[id] = response.data.records || [];
                            this.variationTotals[id] = response.data.total || 0;
                        } catch (error) {
                            this.errors[id] = error.response?.data?.message || 'SKU 加载失败，请稍后重试';
                        } finally {
                            this.loading[id] = false;
                        }
                    },

                    stripHtml(value) {
                        return value === null || value === undefined
                            ? ''
                            : String(value).replace(/<[^>]*>/g, '').trim();
                    },
                },
            });
        </script>

        <script type="text/x-template" id="v-create-product-form-template">
            <div>
                <!-- Product Create Button -->
                @if (bouncer()->hasPermission('catalog.products.create'))
                    <button
                        type="button"
                        class="primary-button"
                        @click="$refs.productCreateModal.toggle()"
                    >
                        @lang('admin::app.catalog.products.index.create-btn')
                    </button>
                @endif

                <x-admin::form
                    v-slot="{ meta, errors, handleSubmit }"
                    as="div"
                >
                    <form @submit="handleSubmit($event, create)" ref="productCreateForm">
                        <!-- Customer Create Modal -->
                        <x-admin::modal ref="productCreateModal">
                            <!-- Modal Header -->
                            <x-slot:header>
                                <p
                                    class="text-lg text-gray-800 dark:text-white font-bold"
                                    v-if="! variantStructures.length"
                                >
                                    @lang('admin::app.catalog.products.index.create.title')
                                </p>

                                <p
                                    class="text-lg text-gray-800 dark:text-white font-bold"
                                    v-else
                                >
                                    @lang('admin::app.catalog.products.index.create.variant-structure')
                                </p>
                            </x-slot>

                            <!-- Modal Content -->
                            <x-slot:content>
                                <div v-show="! variantStructures.length">
                                    {!! view_render_event('unopim.admin.catalog.products.create_form.general.controls.before') !!}

                                    <!-- Product Type -->
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.label class="required">
                                            @lang('admin::app.catalog.products.index.create.type')
                                        </x-admin::form.control-group.label>

                                        @php
                                            $supportedTypes  = config('product_types');

                                            $types = [];

                                            foreach($supportedTypes as $id => $type) {
                                                if ($type['internal'] ?? false) {
                                                    continue;
                                                }

                                                $types[] = [
                                                    'id'    => $id,
                                                    'label' => trans($type['name'])
                                                ];
                                            }
                                        @endphp

                                        <x-admin::form.control-group.control
                                            type="select"
                                            name="type"
                                            rules="required"
                                            :label="trans('admin::app.catalog.products.index.create.type')"
                                            :options="json_encode($types)"
                                            track-by="id"
                                            label-by="label"
                                            @input="type = $event ? JSON.parse($event).id : null"
                                        >
                                        </x-admin::form.control-group.control>

                                        <x-admin::form.control-group.error control-name="type" />
                                    </x-admin::form.control-group>

                                    <!-- Attribute Family Id -->
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.label class="required">
                                            @lang('admin::app.catalog.products.index.create.family')
                                        </x-admin::form.control-group.label>

                                        <x-admin::form.control-group.control
                                            type="select"
                                            name="attribute_family_id"
                                            rules="required"
                                            :label="trans('admin::app.catalog.products.index.create.family')"
                                            entity-name="attribute_family"
                                            track-by="id"
                                            label-by="label"
                                            async="true"
                                        >
                                        </x-admin::form.control-group.control>

                                        <x-admin::form.control-group.error control-name="attribute_family_id" />
                                    </x-admin::form.control-group>

                                    <!-- SKU -->
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.label class="required">
                                            @lang('admin::app.catalog.products.index.create.sku')
                                        </x-admin::form.control-group.label>

                                        <x-admin::form.control-group.control
                                            type="text"
                                            name="sku"
                                            ::rules="{ required: true, regex: /^[a-zA-Z0-9]+(?:[-_][a-zA-Z0-9]+)*$/ }"
                                            :label="trans('admin::app.catalog.products.index.create.sku')"
                                        />

                                        <x-admin::form.control-group.error control-name="sku" />
                                    </x-admin::form.control-group>

                                    {!! view_render_event('unopim.admin.catalog.products.create_form.general.controls.before') !!}
                                </div>

                                <div v-if="variantStructures.length">
                                    {!! view_render_event('unopim.admin.catalog.products.create_form.attributes.controls.before') !!}

                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.label class="required">
                                            @lang('admin::app.catalog.products.index.create.variant-structure')
                                        </x-admin::form.control-group.label>

                                        <x-admin::form.control-group.control
                                            type="select"
                                            name="variant_structure_id"
                                            rules="required"
                                            :label="trans('admin::app.catalog.products.index.create.variant-structure')"
                                            ::options="JSON.stringify(variantStructures.map(s => ({ id: s.id, label: s.name + ' (' + (s.levels == 2 ? 2 : 1) + '-level)' })))"
                                            track-by="id"
                                            label-by="label"
                                        />

                                        <x-admin::form.control-group.error control-name="variant_structure_id" />
                                    </x-admin::form.control-group>

                                    {!! view_render_event('unopim.admin.catalog.products.create_form.attributes.controls.before') !!}
                                </div>
                            </x-slot>

                            <!-- Modal Footer -->
                            <x-slot:footer>
                                <!-- Modal Submission -->
                                <div class="flex gap-x-2.5 items-center">
                                    <button
                                        type="button"
                                        class="transparent-button hover:bg-primary-100 dark:hover:bg-gray-800 dark:text-white"
                                        v-if="variantStructures.length"
                                        @click="variantStructures = []"
                                    >
                                        @lang('admin::app.catalog.products.index.create.back-btn')
                                    </button>

                                    <button
                                        type="submit"
                                        class="primary-button"
                                    >
                                        <span v-if="type === 'configurable' && ! variantStructures.length">
                                            @lang('admin::app.catalog.products.index.create.next-btn')
                                        </span>

                                        <span v-else>
                                            @lang('admin::app.catalog.products.index.create.save-btn')
                                        </span>
                                    </button>
                                </div>
                            </x-slot>
                        </x-admin::modal>
                    </form>
                </x-admin::form>
            </div>
        </script>

        <script type="module">
            app.component('v-create-product-form', {
                template: '#v-create-product-form-template',

                data() {
                    return {
                        type: null,

                        variantStructures: [],
                    };
                },

                methods: {
                    create(params, { setErrors }) {
                        let formData = new FormData(this.$refs.productCreateForm);

                        this.$axios.post("{{ route('admin.catalog.products.store') }}", formData)
                            .then((response) => {
                                if (response.data.data.redirect_url) {
                                    this.$navigate(response.data.data.redirect_url);
                                } else if (response.data.data.variant_structures) {
                                    this.variantStructures = response.data.data.variant_structures;
                                }
                            })
                            .catch(error => {
                                if (error.response.status == 422) {
                                    setErrors(error.response.data.errors);
                                }
                            });
                    },
                }
            })
        </script>
        @endPushOnce
</x-admin::layouts>
