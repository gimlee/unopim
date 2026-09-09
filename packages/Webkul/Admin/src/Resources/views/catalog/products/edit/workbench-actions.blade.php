<v-product-workbench-actions
    :product-id="{{ $product->id }}"
    sku="{{ $product->sku }}"
    ai-optimize-url="{{ route('admin.catalog.products.ai_optimize', $product->id) }}"
    listing-draft-url="{{ route('admin.catalog.products.listing_draft', $product->id) }}"
    listing-status-url="{{ route('admin.catalog.products.listing_status', $product->id) }}"
    listing-cancel-url="{{ route('admin.catalog.products.listing_cancel', $product->id) }}"
></v-product-workbench-actions>

@pushOnce('scripts')
    <script type="text/x-template" id="v-product-workbench-actions-template">
        <div class="flex flex-col gap-2 items-end">
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
                        class="secondary-button inline-flex items-center gap-1.5 bg-purple-50 text-purple-700 border-purple-200 hover:bg-purple-100 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-800"
                        :disabled="isAiOptimizing || isListingDrafting || isListingSubmitting"
                        @click="openImageTranslationModal"
                    >
                        <span class="icon-camera text-lg"></span>
                        <span>图片翻译</span>
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
                        <span v-text="isListingDrafting ? `上架草稿中 (${activeListingRegions.join(', ')})` : '上架草稿'"></span>
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
                        <span v-text="isListingSubmitting ? `上架审核中 (${activeListingRegions.join(', ')})` : '上架审核'"></span>
                    </button>

                    <button
                        v-if="isListingDrafting || isListingSubmitting"
                        type="button"
                        class="secondary-button inline-flex items-center gap-1.5 bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100 dark:bg-rose-900/30 dark:text-rose-300 dark:border-rose-800"
                        :disabled="isCancellingListing"
                        @click="handleListingCancel"
                    >
                        <img
                            v-if="isCancellingListing"
                            class="h-4 w-4 animate-spin"
                            src="{{ unopim_asset('images/spinner.svg') }}"
                        />
                        <span v-else class="icon-cross text-lg"></span>
                        <span v-text="isCancellingListing ? '正在停止…' : '停止上架'"></span>
                    </button>
                @endif
            </div>

            @if (bouncer()->hasPermission('catalog.products.edit'))
                <div class="flex items-center gap-1.5 text-xs">
                    <span class="text-gray-500 dark:text-gray-400 select-none">上架地区:</span>
                    <button
                        v-for="reg in candidateRegions"
                        :key="reg.code"
                        type="button"
                        :disabled="isListingDrafting || isListingSubmitting"
                        class="px-2 py-0.5 rounded border transition inline-flex items-center gap-1 select-none font-medium cursor-pointer"
                        :class="selectedRegions.includes(reg.code)
                            ? 'bg-blue-50 border-blue-400 text-blue-700 dark:bg-blue-900/40 dark:border-blue-500 dark:text-blue-300 font-semibold'
                            : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300 dark:bg-cherry-900 dark:border-cherry-700 dark:text-gray-400'"
                        :title="reg.name"
                        @click="toggleRegion(reg.code)"
                    >
                        <svg v-if="selectedRegions.includes(reg.code)" class="w-3 h-3 text-blue-600 dark:text-blue-400 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                        <span v-text="reg.code"></span>
                    </button>
                </div>
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
                listingCancelUrl: {
                    type: String,
                    required: true,
                },
            },

            data() {
                return {
                    isAiOptimizing: false,
                    isListingDrafting: false,
                    isListingSubmitting: false,
                    isCancellingListing: false,
                    activeListingRegions: [],
                    candidateRegions: [
                        { code: 'MY', name: '马来西亚' },
                        { code: 'TH', name: '泰国' },
                        { code: 'VN', name: '越南' },
                        { code: 'PH', name: '菲律宾' },
                        { code: 'SG', name: '新加坡' },
                    ],
                    selectedRegions: ['MY', 'TH'],
                    isFormDirty: false,
                    dirtyHandler: null,
                    listingPollTimers: {},
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

                this.clearAllPollTimers();
            },

            methods: {
                emitFlash(type, message) {
                    this.$emitter?.emit('add-flash', { type, message });
                    window.dispatchEvent(new CustomEvent('unopim:flash', { detail: { type, message } }));
                },

                openImageTranslationModal() {
                    const emitter = this.$emitter || window.app?.config?.globalProperties?.$emitter;
                    emitter?.emit('open-image-translation-modal');
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
                                    } else if (descRes.data?.data?.description) {
                                        this.$emitter?.emit('product-description-ai:apply', descRes.data.data.description);
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

                toggleRegion(code) {
                    if (this.selectedRegions.includes(code)) {
                        if (this.selectedRegions.length === 1) {
                            this.emitFlash('warning', '请至少保留一个上架地区。');
                            return;
                        }
                        this.selectedRegions = this.selectedRegions.filter(c => c !== code);
                    } else {
                        this.selectedRegions.push(code);
                    }
                },

                clearPollTimer(region) {
                    if (this.listingPollTimers[region]) {
                        clearInterval(this.listingPollTimers[region]);
                        delete this.listingPollTimers[region];
                    }
                },

                clearAllPollTimers() {
                    Object.keys(this.listingPollTimers).forEach(reg => {
                        clearInterval(this.listingPollTimers[reg]);
                    });
                    this.listingPollTimers = {};
                },

                async handleListingCancel() {
                    this.isCancellingListing = true;
                    try {
                        await this.$axios.post(this.listingCancelUrl, {});
                        this.clearAllPollTimers();
                        this.emitFlash('info', '已发送停止指令，正在终止上架进程并关闭浏览器...');
                    } catch (error) {
                        const msg = error.response?.data?.message || error.message || '停止上架请求失败';
                        this.emitFlash('warning', msg);
                    } finally {
                        this.clearAllPollTimers();
                        this.isListingDrafting = false;
                        this.isListingSubmitting = false;
                        this.isCancellingListing = false;
                        this.activeListingRegions = [];
                        this.emitFlash('success', '上架已停止，操作按钮已重置。');
                    }
                },

                handleListingDraft() {
                    this.runRegionalListing('draft', false);
                },

                handleListingSubmit() {
                    this.runRegionalListing('submit', true);
                },

                runRegionalListing(action, isSubmit) {
                    if (this.hasUnsavedChanges()) {
                        const actionName = isSubmit ? '上架审核' : '上架草稿';
                        this.emitFlash('warning', `当前商品信息有修改未保存，请先保存当前页面信息后再执行${actionName}。`);
                        return;
                    }

                    if (! this.selectedRegions || ! this.selectedRegions.length) {
                        this.emitFlash('warning', '请至少勾选一个上架地区（如 MY、TH）。');
                        return;
                    }

                    const regionsToRun = [...this.selectedRegions];
                    const actionLabel = isSubmit ? '上架审核' : '上架草稿';

                    this.$emitter?.emit('open-confirm-modal', {
                        title: actionLabel,
                        message: `确定对该商品在选定地区 [${regionsToRun.join(', ')}] 并发执行 TikTok Shop ${actionLabel} 吗？系统将复用同一个 Chrome 登录会话，并在各地区的独立页面中并行填报（仅登录、验证码或安全验证需要人工处理）。`,
                        options: {
                            btnAgree: `并发${actionLabel}`,
                            btnDisagree: '取消',
                            btnAgreeClass: 'primary-button',
                            btnDisagreeClass: 'transparent-button',
                        },
                        agree: async () => {
                            if (isSubmit) {
                                this.isListingSubmitting = true;
                            } else {
                                this.isListingDrafting = true;
                            }
                            this.activeListingRegions = [...regionsToRun];
                            this.emitFlash('info', `正在使用共享 Chrome 登录会话，为 [${regionsToRun.join(', ')}] 打开独立页面执行 ${actionLabel}...`);

                            try {
                                const tasks = regionsToRun.map(region => this.executeSingleRegionListing(region, action, isSubmit));
                                const results = await Promise.allSettled(tasks);

                                let successCount = 0;
                                let failCount = 0;
                                results.forEach((res) => {
                                    if (res.status === 'fulfilled' && res.value?.status !== 'cancelled') {
                                        successCount++;
                                    } else {
                                        failCount++;
                                    }
                                });

                                if (failCount === 0) {
                                    this.emitFlash('success', `选定地区 [${regionsToRun.join(', ')}] 的 ${actionLabel} 已全部成功完成！`);
                                } else if (successCount > 0) {
                                    this.emitFlash('warning', `[${regionsToRun.join(', ')}] 的 ${actionLabel} 执行完毕：${successCount} 个成功，${failCount} 个未完成/取消。`);
                                }
                            } catch (err) {
                                this.emitFlash('error', err.message || '上架并发执行出现异常');
                            } finally {
                                this.clearAllPollTimers();
                                this.isListingDrafting = false;
                                this.isListingSubmitting = false;
                                this.activeListingRegions = [];
                            }
                        }
                    });
                },

                executeSingleRegionListing(region, action, isSubmit) {
                    return new Promise((resolve, reject) => {
                        this.$axios.post(this.listingDraftUrl, {
                            region: region,
                            action: action,
                            submit_for_review: Boolean(isSubmit),
                        }).then(response => {
                            this.pollListingStatusForRegion(region, isSubmit, resolve, reject);
                        }).catch(error => {
                            const msg = error.response?.data?.message || `[${region}] 上架启动失败`;
                            this.emitFlash('error', msg);
                            reject(new Error(msg));
                        });
                    });
                },

                pollListingStatusForRegion(region, isSubmit, resolve, reject) {
                    this.clearPollTimer(region);

                    let pollCount = 0;
                    const maxPolls = 150; // 5 分钟超时 (2秒 * 150)
                    const url = `${this.listingStatusUrl}?region=${encodeURIComponent(region)}`;

                    this.listingPollTimers[region] = setInterval(() => {
                        pollCount++;
                        if (pollCount > maxPolls) {
                            this.clearPollTimer(region);
                            const timeoutMsg = `[${region}] 上架任务执行时间较长，请检查后台或已弹出的浏览器窗口。`;
                            this.emitFlash('warning', timeoutMsg);
                            reject(new Error(timeoutMsg));
                            return;
                        }

                        this.$axios.get(url)
                            .then(response => {
                                const data = response.data?.data;
                                if (! data) return;

                                const status = data.status;

                                if (status === 'draft_saved' || status === 'form_filled') {
                                    this.clearPollTimer(region);
                                    this.emitFlash('success', `[${region}] TikTok Shop 草稿已生成并保存成功！`);
                                    resolve(data);
                                } else if (status === 'submitted_for_review') {
                                    this.clearPollTimer(region);
                                    this.emitFlash('success', `[${region}] TikTok Shop 上架审核提交成功！`);
                                    resolve(data);
                                } else if (status === 'cancelled') {
                                    this.clearPollTimer(region);
                                    this.emitFlash('info', `[${region}] 上架已取消。`);
                                    resolve(data);
                                } else if (status === 'failed' || status === 'draft_save_unconfirmed') {
                                    this.clearPollTimer(region);
                                    const errorMsg = data.error || (status === 'draft_save_unconfirmed' ? '草稿保存未获权威确认' : '执行失败');
                                    this.emitFlash('error', `[${region}] 上架自动化失败: ${errorMsg}`);
                                    reject(new Error(`[${region}] 上架失败: ${errorMsg}`));
                                }
                            })
                            .catch(() => {
                                // 忽略偶发网络轮询错误，继续轮询
                            });
                    }, 2000);
                },
            }
        });
    </script>
@endPushOnce
