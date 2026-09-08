<v-product-workbench-actions
    :product-id="{{ $product->id }}"
    sku="{{ $product->sku }}"
    ai-optimize-url="{{ route('admin.catalog.products.ai_optimize', $product->id) }}"
    listing-draft-url="{{ route('admin.catalog.products.listing_draft', $product->id) }}"
    listing-status-url="{{ route('admin.catalog.products.listing_status', $product->id) }}"
></v-product-workbench-actions>

@pushOnce('scripts')
    <script type="text/x-template" id="v-product-workbench-actions-template">
        <div class="flex items-center gap-2">
            @if (bouncer()->hasPermission('catalog.products.edit'))
                <button
                    type="button"
                    class="secondary-button inline-flex items-center gap-1.5"
                    :disabled="isAiOptimizing || isListingDrafting || isListingSubmitting"
                    @click="handleAiOptimize"
                >
                    <img
                        v-if="isAiOptimizing"
                        class="h-4 w-4 animate-spin"
                        src="{{ unopim_asset('images/spinner.svg') }}"
                    />
                    <span v-else class="icon-magic text-lg"></span>
                    <span v-text="isAiOptimizing ? 'AI 优化中…' : 'AI 优化'"></span>
                </button>

                <button
                    type="button"
                    class="secondary-button inline-flex items-center gap-1.5 bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800"
                    :disabled="isAiOptimizing || isListingDrafting || isListingSubmitting"
                    @click="handleListingDraft"
                >
                    <img
                        v-if="isListingDrafting"
                        class="h-4 w-4 animate-spin"
                        src="{{ unopim_asset('images/spinner.svg') }}"
                    />
                    <span v-else class="icon-export text-lg"></span>
                    <span v-text="isListingDrafting ? '上架草稿中…' : '上架草稿'"></span>
                </button>

                <button
                    type="button"
                    class="secondary-button inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800"
                    :disabled="isAiOptimizing || isListingDrafting || isListingSubmitting"
                    @click="handleListingSubmit"
                >
                    <img
                        v-if="isListingSubmitting"
                        class="h-4 w-4 animate-spin"
                        src="{{ unopim_asset('images/spinner.svg') }}"
                    />
                    <span v-else class="icon-check text-lg"></span>
                    <span v-text="isListingSubmitting ? '上架审核中…' : '上架审核'"></span>
                </button>
            @endif
        </div>
    </script>

    <script type="module">
        app.component('v-product-workbench-actions', {
            template: '#v-product-workbench-actions-template',

            props: {
                productId: {
                    type: [Number, String],
                    required: true,
                },
                sku: {
                    type: String,
                    required: true,
                },
                aiOptimizeUrl: {
                    type: String,
                    required: true,
                },
                listingDraftUrl: {
                    type: String,
                    required: true,
                },
                listingStatusUrl: {
                    type: String,
                    required: true,
                },
            },

            data() {
                return {
                    isAiOptimizing: false,
                    isListingDrafting: false,
                    isListingSubmitting: false,
                    isFormDirty: false,
                    dirtyHandler: null,
                    listingPollTimer: null,
                };
            },

            mounted() {
                this.dirtyHandler = ({ dirty }) => {
                    this.isFormDirty = Boolean(dirty);
                };

                this.$emitter?.on('unsaved-changes:state', this.dirtyHandler);
            },

            beforeUnmount() {
                if (this.dirtyHandler) {
                    this.$emitter?.off('unsaved-changes:state', this.dirtyHandler);
                }

                if (this.listingPollTimer) {
                    clearInterval(this.listingPollTimer);
                    this.listingPollTimer = null;
                }
            },

            methods: {
                emitFlash(type, message) {
                    this.$emitter?.emit('add-flash', { type, message });
                    window.dispatchEvent(new CustomEvent('unopim:flash', { detail: { type, message } }));
                },

                hasUnsavedChanges() {
                    if (this.isFormDirty) {
                        return true;
                    }

                    if (typeof window.__unsavedBarCount === 'number' && window.__unsavedBarCount > 0) {
                        return true;
                    }

                    const unsavedBar = document.querySelector('.unsaved-bar');
                    if (unsavedBar && unsavedBar.offsetParent !== null) {
                        return true;
                    }

                    return false;
                },

                handleAiOptimize() {
                    this.$emitter?.emit('open-confirm-modal', {
                        title: 'AI 优化',
                        message: '确定要对当前商品执行 AI 优化吗？将按顺序检查并优化分类、商品名称与商品描述，已完成项将自动跳过。',
                        options: {
                            btnAgree: '开始优化',
                            btnDisagree: '取消',
                            btnAgreeClass: 'primary-button',
                            btnDisagreeClass: 'transparent-button',
                        },
                        agree: async () => {
                            this.isAiOptimizing = true;
                            const params = new URLSearchParams(window.location.search);
                            const locale = params.get('locale') || 'zh_CN';
                            const channel = params.get('channel') || 'default';

                            try {
                                // 1. 分类优化
                                try {
                                    const catRes = await this.$axios.post(this.aiOptimizeUrl, { locale, channel, stage: 'category' });
                                    if (catRes.data?.data?.category?.skipped) {
                                        this.emitFlash('info', '分类已完成，跳过。');
                                    } else if (catRes.data?.data?.category) {
                                        this.emitFlash('success', '已完成商品分类优化！');
                                    }
                                } catch (catErr) {
                                    const msg = catErr.response?.data?.message || '商品分类优化未完成';
                                    this.emitFlash('warning', msg);
                                }

                                // 2. 名称优化（联动名称按钮处理中状态并通知）
                                this.$emitter?.emit('product-name-ai:set-optimizing', true);
                                try {
                                    const nameRes = await this.$axios.post(this.aiOptimizeUrl, { locale, channel, stage: 'name' });
                                    if (nameRes.data?.data?.name?.skipped) {
                                        this.emitFlash('info', '商品名称已优化，跳过。');
                                    } else if (nameRes.data?.data?.name?.name) {
                                        this.$emitter?.emit('product-name-ai:apply', nameRes.data.data.name.name);
                                        this.emitFlash('success', '已完成商品名称优化！');
                                    }
                                } catch (nameErr) {
                                    const msg = nameErr.response?.data?.message || '商品名称优化失败';
                                    this.emitFlash('error', msg);
                                } finally {
                                    this.$emitter?.emit('product-name-ai:set-optimizing', false);
                                }

                                // 3. 描述优化（联动描述按钮处理中状态并通知）
                                this.$emitter?.emit('product-description-ai:set-optimizing', true);
                                try {
                                    const descRes = await this.$axios.post(this.aiOptimizeUrl, { locale, channel, stage: 'description' });
                                    if (descRes.data?.data?.description?.skipped) {
                                        this.emitFlash('info', '商品描述已优化，跳过。');
                                    } else if (descRes.data?.data?.description?.short_description) {
                                        this.$emitter?.emit('product-description-ai:apply', descRes.data.data.description.short_description);
                                        this.emitFlash('success', '已完成商品描述优化！');
                                    }
                                } catch (descErr) {
                                    const msg = descErr.response?.data?.message || '商品描述优化失败';
                                    this.emitFlash('error', msg);
                                } finally {
                                    this.$emitter?.emit('product-description-ai:set-optimizing', false);
                                }

                                this.emitFlash('success', '所有 AI 优化流程执行完毕！');
                            } catch (e) {
                                this.emitFlash('error', e.message || 'AI 优化执行异常');
                            } finally {
                                this.isAiOptimizing = false;
                            }
                        }
                    });
                },

                pollListingStatus(isSubmit) {
                    if (this.listingPollTimer) {
                        clearInterval(this.listingPollTimer);
                        this.listingPollTimer = null;
                    }

                    let pollCount = 0;
                    const maxPolls = 150; // 5 分钟超时 (2秒 * 150)

                    this.listingPollTimer = setInterval(() => {
                        pollCount++;
                        if (pollCount > maxPolls) {
                            clearInterval(this.listingPollTimer);
                            this.listingPollTimer = null;
                            this.isListingDrafting = false;
                            this.isListingSubmitting = false;
                            this.emitFlash('warning', '上架任务执行时间较长，请检查后台或已弹出的浏览器窗口。');
                            return;
                        }

                        this.$axios.get(this.listingStatusUrl)
                            .then(response => {
                                const data = response.data?.data;
                                if (! data) return;

                                const status = data.status;

                                if (status === 'draft_saved' || status === 'form_filled') {
                                    clearInterval(this.listingPollTimer);
                                    this.listingPollTimer = null;
                                    this.isListingDrafting = false;
                                    this.isListingSubmitting = false;
                                    this.emitFlash('success', 'TikTok Shop 草稿已生成并保存成功！');
                                } else if (status === 'submitted_for_review') {
                                    clearInterval(this.listingPollTimer);
                                    this.listingPollTimer = null;
                                    this.isListingDrafting = false;
                                    this.isListingSubmitting = false;
                                    this.emitFlash('success', 'TikTok Shop 上架审核提交成功！');
                                } else if (status === 'failed' || status === 'draft_save_unconfirmed') {
                                    clearInterval(this.listingPollTimer);
                                    this.listingPollTimer = null;
                                    this.isListingDrafting = false;
                                    this.isListingSubmitting = false;
                                    const errorMsg = data.error || (status === 'draft_save_unconfirmed' ? '草稿保存未获权威确认' : '执行失败');
                                    this.emitFlash('error', `上架自动化失败: ${errorMsg}`);
                                }
                            })
                            .catch(() => {
                                // 忽略偶发网络轮询错误，继续轮询
                            });
                    }, 2000);
                },

                handleListingDraft() {
                    if (this.hasUnsavedChanges()) {
                        this.emitFlash('warning', '当前商品信息有修改未保存，请先保存当前页面信息后再执行上架草稿。');
                        return;
                    }

                    this.$emitter?.emit('open-confirm-modal', {
                        title: '上架草稿',
                        message: '确定对该商品执行 TikTok Shop 上架草稿吗？系统将调用 OpenCLI 启动 Chrome 浏览器自动填表并保存草稿（若遇验证码请在浏览器中完成）。',
                        options: {
                            btnAgree: '开始上架',
                            btnDisagree: '取消',
                            btnAgreeClass: 'primary-button',
                            btnDisagreeClass: 'transparent-button',
                        },
                        agree: () => {
                            this.isListingDrafting = true;
                            this.emitFlash('info', 'TikTok Shop 上架自动化已启动，正在调起 Chrome 浏览器，请留意屏幕弹出的浏览器窗口...');
                            this.$axios.post(this.listingDraftUrl, {
                                region: 'MY',
                                action: 'draft',
                            })
                            .then(response => {
                                this.pollListingStatus(false);
                            })
                            .catch(error => {
                                this.isListingDrafting = false;
                                this.emitFlash('error', error.response?.data?.message || '上架草稿启动失败');
                            });
                        }
                    });
                },

                handleListingSubmit() {
                    if (this.hasUnsavedChanges()) {
                        this.emitFlash('warning', '当前商品信息有修改未保存，请先保存当前页面信息后再执行上架审核。');
                        return;
                    }

                    this.$emitter?.emit('open-confirm-modal', {
                        title: '上架审核',
                        message: '确定对该商品执行 TikTok Shop 上架审核吗？系统将调用 OpenCLI 启动 Chrome 浏览器自动填表并直接提交审核（若遇验证码请在浏览器中完成）。',
                        options: {
                            btnAgree: '提交审核',
                            btnDisagree: '取消',
                            btnAgreeClass: 'primary-button',
                            btnDisagreeClass: 'transparent-button',
                        },
                        agree: () => {
                            this.isListingSubmitting = true;
                            this.emitFlash('info', 'TikTok Shop 上架自动化已启动，正在调起 Chrome 浏览器，请留意屏幕弹出的浏览器窗口...');
                            this.$axios.post(this.listingDraftUrl, {
                                region: 'MY',
                                action: 'submit',
                                submit_for_review: true,
                            })
                            .then(response => {
                                this.pollListingStatus(true);
                            })
                            .catch(error => {
                                this.isListingSubmitting = false;
                                this.emitFlash('error', error.response?.data?.message || '上架审核启动失败');
                            });
                        }
                    });
                },
            }
        });
    </script>
@endPushOnce
