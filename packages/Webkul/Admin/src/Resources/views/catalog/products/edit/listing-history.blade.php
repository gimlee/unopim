<v-product-listing-history
    product-id="{{ $productId }}"
></v-product-listing-history>

@pushOnce('scripts')
    <script type="text/x-template" id="v-product-listing-history-template">
        <x-admin::product.section-drawer
            id="listing-history"
            title="上架历史"
            subtitle="草稿与审核上架结果"
            icon="icon-history"
        >
            <x-slot:toggle>
                <div @click="fetchHistories(false)">
                    <x-admin::product.section-card
                        id="listing-history"
                        title="上架历史"
                        icon="icon-history"
                    >
                        <span v-text="summary"></span>
                    </x-admin::product.section-card>
                </div>
            </x-slot:toggle>

            <x-slot:headerActions>
                <button
                    type="button"
                    class="secondary-button !h-8 shrink-0 !px-3 inline-flex items-center gap-1.5"
                    :disabled="isRefreshing"
                    @click="fetchHistories(true)"
                >
                    <span class="icon-refresh text-base" :class="{'animate-spin': isRefreshing}"></span>
                    <span>刷新</span>
                </button>
            </x-slot:headerActions>

            <x-slot:content>
                <div v-if="! histories.length" class="rounded border border-dashed p-5 text-center text-sm text-gray-500 dark:border-cherry-700">
                    暂无上架历史
                </div>

                <div v-else class="space-y-2">
                    <article
                        v-for="item in histories"
                        :key="item.id"
                        class="rounded border border-gray-200 bg-white p-3 dark:border-cherry-700 dark:bg-cherry-900"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="mb-2 flex flex-wrap items-center gap-1.5">
                                    <span class="rounded bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-700" v-text="platformLabel(item.platform)"></span>
                                    <span class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-700" v-text="item.region || '-' "></span>
                                    <span class="rounded bg-violet-100 px-2 py-1 text-xs text-violet-700" v-text="typeLabel(item.listing_type)"></span>
                                    <span class="rounded px-2 py-1 text-xs font-semibold" :class="statusClass(item.status)" v-text="statusLabel(item.status)"></span>
                                </div>

                                <p class="text-xs text-gray-500">
                                    <span v-text="`时间：${displayTime(item)}`"></span>
                                    <span v-if="item.attempt_id" v-text="` · 尝试：${item.attempt_id}`"></span>
                                </p>
                                <p v-if="item.error" class="mt-2 whitespace-pre-line break-words text-sm text-red-600" v-text="item.error"></p>
                            </div>

                            <a
                                v-if="resultUrl(item)"
                                class="secondary-button !h-8 shrink-0 !px-3"
                                :href="resultUrl(item)"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                查看结果
                            </a>
                        </div>
                    </article>
                </div>
            </x-slot:content>
        </x-admin::product.section-drawer>
    </script>

    <script type="module">
        app.component('v-product-listing-history', {
            template: '#v-product-listing-history-template',

            props: ['productId'],

            data() {
                return {
                    histories: @json($histories->values()),
                    isRefreshing: false,
                };
            },

            computed: {
                summary() {
                    if (! this.histories.length) return '暂无上架历史';
                    const latest = this.histories[0];
                    return `共 ${this.histories.length} 条 · ${this.platformLabel(latest.platform)} ${latest.region || '-'} ${this.typeLabel(latest.listing_type)} ${this.statusLabel(latest.status)}`;
                },
            },

            methods: {
                platformLabel(platform) {
                    return String(platform || 'tiktok').toUpperCase();
                },

                typeLabel(type) {
                    return type === 'review' ? '上架审核' : '上架草稿';
                },

                statusLabel(status) {
                    return {
                        pending: '等待执行',
                        starting: '正在启动',
                        filling: '正在填报',
                        form_filled: '已完成填表',
                        saved: '草稿已保存',
                        draft_saved: '草稿已保存',
                        submitted: '已提交审核',
                        submitted_for_review: '已提交审核',
                        submit_unconfirmed: '审核提交未确认',
                        success: '成功',
                        failed: '失败',
                        cancelled: '已取消',
                        timeout: '已超时',
                        draft_save_unconfirmed: '草稿未确认',
                    }[status] || status || '未知状态';
                },

                statusClass(status) {
                    if (['form_filled', 'saved', 'draft_saved', 'submitted', 'submitted_for_review', 'success'].includes(status)) return 'bg-green-100 text-green-700';
                    if (['failed', 'timeout', 'draft_save_unconfirmed', 'submit_unconfirmed'].includes(status)) return 'bg-red-100 text-red-700';
                    if (status === 'cancelled') return 'bg-gray-100 text-gray-600';
                    return 'bg-amber-100 text-amber-700';
                },

                displayTime(item) {
                    return item.completed_at || item.started_at || item.created_at || '-';
                },

                resultUrl(item) {
                    return item.draft_url || item.seller_url || null;
                },

                fetchHistories(showFlash = true) {
                    if (this.isRefreshing) return;
                    this.isRefreshing = true;
                    const url = @json(route('admin.catalog.products.listing_history.index', ['productId' => '__PRODUCT__']))
                        .replace('__PRODUCT__', this.productId);

                    this.$axios.get(url)
                        .then(response => {
                            this.histories = response.data.data || [];
                            if (showFlash) {
                                this.$emitter.emit('add-flash', { type: 'success', message: '上架历史已刷新。' });
                            }
                        })
                        .catch(error => {
                            if (showFlash) {
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: error.response?.data?.message || '刷新上架历史失败。',
                                });
                            }
                        })
                        .finally(() => this.isRefreshing = false);
                },
            },
        });
    </script>
@endPushOnce
