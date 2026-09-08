<v-product-listing-exceptions
    product-id="{{ $productId }}"
></v-product-listing-exceptions>

@pushOnce('scripts')
    <script type="text/x-template" id="v-product-listing-exceptions-template">
        <x-admin::product.section-drawer
            id="listing-exceptions"
            title="上架异常记录"
            subtitle="验证码、弹窗确认、草稿保存及自动化异常"
            icon="icon-information"
        >
            <x-slot:toggle>
                <x-admin::product.section-card
                    id="listing-exceptions"
                    title="上架异常记录"
                    icon="icon-information"
                >
                    <span v-text="summary"></span>
                </x-admin::product.section-card>
            </x-slot:toggle>

            <x-slot:content>
                <div v-if="! exceptions.length" class="rounded border border-dashed p-5 text-center text-sm text-gray-500 dark:border-cherry-700">
                    暂无上架异常记录
                </div>

                <div v-else class="space-y-2">
                    <article
                        v-for="item in exceptions"
                        :key="item.id"
                        class="rounded border border-gray-200 bg-white p-3 dark:border-cherry-700 dark:bg-cherry-900"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="mb-1.5 flex flex-wrap items-center gap-1.5">
                                    <span class="rounded px-2 py-1 text-xs font-semibold" :class="severityClass(item.severity)" v-text="typeLabel(item.exception_type)"></span>
                                    <span v-if="item.blocking" class="rounded bg-red-100 px-2 py-1 text-xs text-red-700">阻断流程</span>
                                    <span v-if="item.requires_manual" class="rounded bg-amber-100 px-2 py-1 text-xs text-amber-700">需人工确认</span>
                                    <span class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-600" v-text="item.resolved_at ? '已处理' : '未处理'"></span>
                                </div>

                                <p class="whitespace-pre-line break-words text-base leading-6 text-gray-800 dark:text-gray-100" v-text="item.message"></p>
                                <p class="mt-1 text-xs text-gray-500">
                                    <span v-text="`${String(item.platform || '').toUpperCase()}${item.region ? ` / ${item.region}` : ''}`"></span>
                                    <span v-if="item.stage" v-text="` · ${item.stage}`"></span>
                                    <span v-text="` · ${item.occurred_at || item.created_at || ''}`"></span>
                                </p>
                            </div>

                            <button
                                type="button"
                                class="secondary-button !h-8 shrink-0 !px-3"
                                :disabled="savingId === item.id"
                                @click="toggleResolved(item)"
                            >
                                <span v-text="item.resolved_at ? '重新打开' : '标记已处理'"></span>
                            </button>
                        </div>

                        <details v-if="hasDetails(item)" class="mt-2 border-t pt-2 text-sm dark:border-cherry-700">
                            <summary class="cursor-pointer text-primary-600">查看异常上下文</summary>
                            <pre class="mt-2 max-h-56 overflow-auto whitespace-pre-wrap break-words rounded bg-gray-50 p-2 text-xs leading-5 dark:bg-cherry-800" v-text="detailsText(item)"></pre>
                        </details>
                    </article>
                </div>
            </x-slot:content>
        </x-admin::product.section-drawer>
    </script>

    <script type="module">
        app.component('v-product-listing-exceptions', {
            template: '#v-product-listing-exceptions-template',

            props: ['productId'],

            data() {
                return {
                    exceptions: @json($exceptions->values()),
                    savingId: null,
                };
            },

            computed: {
                summary() {
                    const unresolved = this.exceptions.filter(item => ! item.resolved_at).length;
                    return unresolved ? `未处理 ${unresolved} / 共 ${this.exceptions.length}` : `共 ${this.exceptions.length} 条，已全部处理`;
                },
            },

            methods: {
                typeLabel(type) {
                    return {
                        captcha: '验证码/安全验证',
                        popup_confirmation: '弹窗确认',
                        popup_blocked: '阻断弹窗',
                        image_crop_confirmation: '图片裁剪确认',
                        draft_save_failed: '草稿保存失败',
                        automation_timeout: '自动化超时',
                        listing_data_error: '上架数据异常',
                        automation_error: '自动化异常',
                        browser_exception: '浏览器异常',
                    }[type] || type || '其他异常';
                },

                severityClass(severity) {
                    return severity === 'error'
                        ? 'bg-red-100 text-red-700'
                        : (severity === 'info' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700');
                },

                hasDetails(item) {
                    return item.details && Object.keys(item.details).length > 0;
                },

                detailsText(item) {
                    return JSON.stringify(item.details || {}, null, 2);
                },

                toggleResolved(item) {
                    if (this.savingId) return;
                    this.savingId = item.id;
                    const url = @json(route('admin.catalog.products.listing_exceptions.update', ['productId' => '__PRODUCT__', 'exceptionId' => '__EXCEPTION__']))
                        .replace('__PRODUCT__', this.productId)
                        .replace('__EXCEPTION__', item.id);

                    this.$axios.patch(url, { resolved: ! item.resolved_at })
                        .then(response => {
                            item.resolved_at = response.data.data.resolved_at;
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                        })
                        .catch(error => {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: error.response?.data?.message || '更新上架异常状态失败。',
                            });
                        })
                        .finally(() => this.savingId = null);
                },
            },
        });
    </script>
@endPushOnce
