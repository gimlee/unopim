<v-product-workbench-actions
    :product-id="{{ $product->id }}"
    sku="{{ $product->sku }}"
    ai-optimize-url="{{ route('admin.catalog.products.ai_optimize', $product->id) }}"
    listing-draft-url="{{ route('admin.catalog.products.listing_draft', $product->id) }}"
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
            },

            data() {
                return {
                    isAiOptimizing: false,
                    isListingDrafting: false,
                    isListingSubmitting: false,
                    isFormDirty: false,
                    dirtyHandler: null,
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
                        message: '确定要对当前商品执行 AI 优化吗？将自动检查并跳过已完成项目，执行未优化的分类、名称与描述。',
                        options: {
                            btnAgree: '开始优化',
                            btnDisagree: '取消',
                            btnAgreeClass: 'primary-button',
                            btnDisagreeClass: 'transparent-button',
                        },
                        agree: () => {
                            this.isAiOptimizing = true;
                            const params = new URLSearchParams(window.location.search);
                            const locale = params.get('locale') || 'zh_CN';
                            const channel = params.get('channel') || 'default';

                            this.$axios.post(this.aiOptimizeUrl, { locale, channel })
                                .then(response => {
                                    this.emitFlash('success', response.data.message || 'AI 优化完成！');

                                    setTimeout(() => {
                                        window.location.reload();
                                    }, 800);
                                })
                                .catch(error => {
                                    this.isAiOptimizing = false;
                                    this.emitFlash('error', error.response?.data?.message || 'AI 优化失败');
                                });
                        }
                    });
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
                            this.emitFlash('info', '正在准备商品物料与数据，即将弹出 Chrome 浏览器执行自动化填表...');
                            this.$axios.post(this.listingDraftUrl, {
                                region: 'MY',
                                action: 'draft',
                            })
                            .then(response => {
                                this.isListingDrafting = false;
                                this.emitFlash('success', response.data.message || 'TikTok Shop 草稿已生成成功！');
                            })
                            .catch(error => {
                                this.isListingDrafting = false;
                                this.emitFlash('error', error.response?.data?.message || '上架草稿失败');
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
                            this.emitFlash('info', '正在准备商品物料与数据，即将弹出 Chrome 浏览器执行自动化填表...');
                            this.$axios.post(this.listingDraftUrl, {
                                region: 'MY',
                                action: 'submit',
                                submit_for_review: true,
                            })
                            .then(response => {
                                this.isListingSubmitting = false;
                                this.emitFlash('success', response.data.message || 'TikTok Shop 上架审核提交成功！');
                            })
                            .catch(error => {
                                this.isListingSubmitting = false;
                                this.emitFlash('error', error.response?.data?.message || '上架审核失败');
                            });
                        }
                    });
                },
            }
        });
    </script>
@endPushOnce
