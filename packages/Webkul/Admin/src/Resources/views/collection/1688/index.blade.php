<x-admin::layouts>
    <x-slot:title>1688 商品采集</x-slot>

    <v-collection-1688-manager>
        <x-admin::page-header title="1688 商品采集" />
        <x-admin::shimmer.datagrid />
    </v-collection-1688-manager>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-collection-1688-manager-template">
            <div>
                <x-admin::page-header title="1688 商品采集">
                    <x-slot:actions>
                        <button
                            type="button"
                            class="secondary-button inline-flex items-center gap-1.5"
                            :disabled="isLoading"
                            @click="loadRecords"
                        >
                            <span class="icon-refresh text-lg" :class="{ 'animate-spin': isLoading }"></span>
                            <span>刷新列表</span>
                        </button>
                    </x-slot:actions>
                </x-admin::page-header>

                <p class="mb-4 text-sm text-gray-500">
                    输入 1688 商品详情链接，后台将自动调起 Chrome 抓取商品快照、提取规格与媒体信息，并直接同步导入 UnoPIM 系统。
                </p>

                <!-- 巨型输入采集卡片 -->
                <div class="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-cherry-800 dark:bg-cherry-900">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-base font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                            <span class="icon-import text-xl text-blue-600"></span>
                            <span>1688 商品详情链接采集</span>
                        </label>
                        <span class="text-xs text-gray-400">支持电脑端/移动端 1688 商品链接（需包含 offer ID）</span>
                    </div>

                    <div class="flex gap-3 items-center">
                        <div class="relative flex-1">
                            <input
                                type="text"
                                v-model="inputUrl"
                                @keyup.enter="startCollection"
                                :disabled="isSubmitting"
                                placeholder="请输入 1688 商品详情链接，例如：https://detail.1688.com/offer/950299271121.html"
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 text-base shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-cherry-700 dark:bg-cherry-800 dark:text-white"
                            />
                            <button
                                v-if="inputUrl"
                                type="button"
                                @click="inputUrl = ''"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                title="清空输入"
                            >
                                <span class="icon-cancel text-xl"></span>
                            </button>
                        </div>

                        <button
                            type="button"
                            class="primary-button inline-flex items-center gap-2 px-6 py-3 text-base font-medium select-none shrink-0"
                            :disabled="isSubmitting || !inputUrl.trim()"
                            @click="startCollection"
                        >
                            <img v-if="isSubmitting" class="h-5 w-5 animate-spin" src="{{ unopim_asset('images/spinner.svg') }}" />
                            <span v-else class="icon-import text-xl"></span>
                            <span v-text="isSubmitting ? '正在提交…' : '开始采集'"></span>
                        </button>
                    </div>

                    <div v-if="submitFeedback" class="mt-3 rounded-lg p-3 text-sm flex items-center gap-2" :class="submitFeedback.success ? 'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-300' : 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300'">
                        <span :class="submitFeedback.success ? 'icon-done' : 'icon-cancel'" class="text-base"></span>
                        <span v-text="submitFeedback.message"></span>
                    </div>
                </div>

                <!-- 任务历史筛选与列表区域 -->
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-cherry-800 dark:bg-cherry-900">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-3.5 dark:border-cherry-800">
                        <div class="flex items-center gap-3">
                            <h3 class="text-base font-semibold text-gray-800 dark:text-white">采集任务列表</h3>
                            <span v-if="isPolling" class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                <span class="h-2 w-2 rounded-full bg-blue-500 animate-pulse"></span>
                                实时同步中
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <!-- 搜索输入框 + 搜索按钮 -->
                            <div class="flex items-center">
                                <div class="relative">
                                    <input
                                        type="text"
                                        v-model="searchTerm"
                                        @keyup.enter="handleSearch"
                                        placeholder="搜索商品名称 / Offer ID / 链接..."
                                        class="w-64 rounded-l-md border border-r-0 border-gray-300 pl-3 pr-7 py-1.5 text-sm focus:outline-none dark:border-cherry-700 dark:bg-cherry-800 dark:text-white"
                                    />
                                    <button
                                        v-if="searchTerm"
                                        type="button"
                                        @click="clearSearch"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                        title="清空"
                                    >
                                        <span class="icon-cancel text-xs"></span>
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    @click="handleSearch"
                                    class="secondary-button !rounded-l-none inline-flex items-center gap-1.5 px-3.5 py-1.5 text-sm font-medium"
                                    title="搜索采集任务"
                                >
                                    <span class="icon-search text-base"></span>
                                    <span>搜索</span>
                                </button>
                            </div>

                            <!-- 状态筛选（筛选项移至右侧并保持间距） -->
                            <select
                                v-model="filterStatus"
                                @change="handleFilterChange"
                                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm dark:border-cherry-700 dark:bg-cherry-800 dark:text-white cursor-pointer"
                            >
                                <option value="">全部状态</option>
                                <option value="running">进行中 (抓取/解析/同步)</option>
                                <option value="success">采集完成 (已入库)</option>
                                <option value="failed">采集失败</option>
                            </select>
                        </div>
                    </div>

                    <!-- 列表内容 -->
                    <div class="overflow-x-auto">
                        <div v-if="isLoading && (!records || records.length === 0)" class="p-8 text-center text-gray-500">
                            <img class="mx-auto h-8 w-8 animate-spin" src="{{ unopim_asset('images/spinner.svg') }}" />
                            <p class="mt-2 text-sm">正在加载采集记录...</p>
                        </div>

                        <div v-else-if="!records || records.length === 0" class="p-12 text-center text-gray-500 dark:text-gray-400">
                            <span class="icon-import text-4xl text-gray-300 dark:text-gray-600"></span>
                            <p class="mt-2 text-base font-medium">暂无符合条件的采集任务</p>
                            <p class="mt-1 text-xs">您可以在上方输入框中粘贴 1688 商品链接并发起首次采集</p>
                        </div>

                        <table v-else class="w-full table-fixed text-left text-sm text-gray-700 dark:text-gray-300">
                            <colgroup>
                                <col style="width: 58px;">
                                <col style="width: auto;">
                                <col style="width: 175px;">
                                <col style="width: 145px;">
                                <col style="width: 105px;">
                            </colgroup>
                            <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500 dark:bg-cherry-800 dark:text-gray-400">
                                <tr>
                                    <th class="px-2.5 py-2.5" style="width: 58px;">缩略图</th>
                                    <th class="px-2 py-2.5">商品名称 / 链接</th>
                                    <th class="px-3 py-2.5 whitespace-nowrap" style="width: 175px;">采集时间</th>
                                    <th class="px-3 py-2.5" style="width: 145px;">采集状态</th>
                                    <th class="px-3 py-2.5 text-right whitespace-nowrap" style="width: 105px;">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-cherry-800">
                                <tr
                                    v-for="record in records"
                                    :key="record.id"
                                    class="hover:bg-gray-50/70 transition-colors dark:hover:bg-cherry-800/50"
                                >
                                    <!-- 缩略图 -->
                                    <td class="px-2.5 py-2 align-middle" style="width: 58px;">
                                        <div class="h-11 w-11 rounded-lg overflow-hidden border border-gray-200 bg-gray-100 flex items-center justify-center dark:border-cherry-700 dark:bg-cherry-800 shrink-0">
                                            <img
                                                v-if="record.thumbnail_url"
                                                :src="record.thumbnail_url"
                                                referrerpolicy="no-referrer"
                                                class="h-full w-full object-cover"
                                                alt="缩略图"
                                                v-on:error="record.thumbnail_url = null"
                                            />
                                            <span v-else class="icon-image text-xl text-gray-400"></span>
                                        </div>
                                    </td>

                                    <!-- 商品名称与详情 -->
                                    <td class="px-2 py-2 align-middle overflow-hidden">
                                        <div class="flex flex-col gap-1 min-w-0">
                                            <a
                                                v-if="record.unopim && record.unopim.admin_url"
                                                :href="record.unopim.admin_url"
                                                class="font-medium text-blue-600 hover:underline truncate block dark:text-blue-400 text-sm"
                                                :title="record.title || record.offer_id"
                                                v-text="record.title || ('1688 商品: ' + record.offer_id)"
                                            ></a>
                                            <span
                                                v-else
                                                class="font-medium text-gray-800 truncate block dark:text-gray-100 text-sm"
                                                :title="record.title || record.offer_id"
                                                v-text="record.title || ('1688 商品: ' + record.offer_id)"
                                            ></span>

                                            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap overflow-hidden">
                                                <span class="inline-flex items-center gap-1 font-mono bg-gray-100 dark:bg-cherry-800 px-1.5 py-0.5 rounded shrink-0">
                                                    ID: @{{ record.offer_id }}
                                                </span>

                                                <a
                                                    :href="record.source_url"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="inline-flex items-center gap-0.5 text-gray-500 hover:text-blue-600 truncate shrink-0 max-w-[280px]"
                                                    :title="record.source_url"
                                                >
                                                    <span class="icon-export text-xs"></span>
                                                    <span class="truncate">1688 原链接</span>
                                                </a>

                                                <a
                                                    v-if="record.unopim && record.unopim.admin_url"
                                                    :href="record.unopim.admin_url"
                                                    class="inline-flex items-center gap-0.5 text-emerald-600 font-medium hover:underline shrink-0"
                                                >
                                                    <span>查看 PIM 商品</span>
                                                    <span class="icon-right text-xs"></span>
                                                </a>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 采集时间 -->
                                    <td class="px-3 py-2 align-middle text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap overflow-hidden" style="width: 175px;">
                                        <div class="whitespace-nowrap font-mono text-[11px] text-gray-600 dark:text-gray-300">
                                            创建: @{{ formatTime(record.created_at) }}
                                        </div>
                                        <div v-if="record.updated_at && record.updated_at !== record.created_at" class="whitespace-nowrap font-mono text-[11px] text-gray-400 mt-0.5">
                                            更新: @{{ formatTime(record.updated_at) }}
                                        </div>
                                    </td>

                                    <!-- 采集状态 -->
                                    <td class="px-3 py-2 align-middle overflow-visible" style="width: 145px;">
                                        <div class="flex flex-col gap-0.5 items-start min-w-0">
                                            <span
                                                v-if="isRunningStatus(record.status)"
                                                class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 whitespace-nowrap"
                                            >
                                                <img class="h-3 w-3 animate-spin" src="{{ unopim_asset('images/spinner.svg') }}" />
                                                <span v-text="statusLabel(record)"></span>
                                            </span>

                                            <span
                                                v-else-if="isSuccessStatus(record.status)"
                                                class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/40 dark:text-green-300 whitespace-nowrap"
                                            >
                                                <span class="icon-done text-sm"></span>
                                                <span>已完成入库</span>
                                            </span>

                                            <span
                                                v-else-if="record.status === 'failed'"
                                                class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/40 dark:text-red-300 whitespace-nowrap"
                                            >
                                                <span class="icon-cancel text-sm"></span>
                                                <span>采集失败</span>
                                            </span>

                                            <span
                                                v-else
                                                class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-cherry-800 dark:text-gray-300 whitespace-nowrap"
                                                v-text="record.status"
                                            ></span>

                                            <!-- 错误详情缩略与悬浮全部显示 -->
                                            <div v-if="record.last_error" class="group relative w-full mt-0.5">
                                                <div
                                                    class="truncate text-xs text-red-500 cursor-help flex items-center gap-0.5"
                                                    :title="record.last_error"
                                                >
                                                    <span class="icon-warning text-xs shrink-0"></span>
                                                    <span class="truncate" v-text="shortError(record.last_error)"></span>
                                                </div>

                                                <div
                                                    class="pointer-events-none invisible group-hover:visible absolute left-0 bottom-full z-[100] mb-1.5 w-80 rounded-lg border border-red-200 bg-white p-2.5 text-xs text-red-700 shadow-xl dark:border-cherry-700 dark:bg-cherry-900 dark:text-red-200"
                                                >
                                                    <div class="font-semibold mb-1 flex items-center gap-1 text-red-600 dark:text-red-400">
                                                        <span class="icon-warning text-sm"></span>
                                                        <span>错误详情</span>
                                                    </div>
                                                    <div class="max-h-48 overflow-y-auto whitespace-pre-wrap break-all select-text font-mono text-[11px] leading-relaxed" v-text="record.last_error"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 操作栏 -->
                                    <td class="px-3 py-2 align-middle text-right whitespace-nowrap" style="width: 105px;">
                                        <div class="flex items-center justify-end gap-1" @click.stop>
                                            <!-- 再次采集 -->
                                            <button
                                                type="button"
                                                class="icon-refresh cursor-pointer rounded p-1 text-xl text-gray-600 hover:bg-blue-50 hover:text-blue-600 dark:text-gray-400 dark:hover:bg-cherry-800 dark:hover:text-blue-400"
                                                :class="{ 'animate-spin': record._retrying }"
                                                :disabled="record._retrying || isRunningStatus(record.status)"
                                                title="再次采集（重新提取名称、规格并同步）"
                                                @click="handleRetry(record)"
                                            ></button>

                                            <!-- 编辑链接 -->
                                            <button
                                                type="button"
                                                class="icon-edit cursor-pointer rounded p-1 text-xl text-gray-600 hover:bg-gray-100 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-cherry-800 dark:hover:text-white"
                                                title="修改采集链接"
                                                @click="openEditModal(record)"
                                            ></button>

                                            <!-- 删除 -->
                                            <button
                                                type="button"
                                                class="icon-delete cursor-pointer rounded p-1 text-xl text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:text-rose-400 dark:hover:bg-cherry-800"
                                                title="删除任务并清空本地文件"
                                                @click="openDeleteModal(record)"
                                            ></button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- 分页导航栏 -->
                    <div v-if="total > 0" class="flex items-center justify-between border-t border-gray-200 px-5 py-3 text-sm text-gray-500 dark:border-cherry-800 dark:text-gray-400">
                        <div>
                            共 <span class="font-semibold text-gray-700 dark:text-gray-200">@{{ total }}</span> 条采集记录
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="secondary-button px-3 py-1 text-xs"
                                :disabled="currentPage <= 1 || isLoading"
                                @click="changePage(currentPage - 1)"
                            >
                                上一页
                            </button>
                            <span class="text-xs">第 @{{ currentPage }} 页 / 共 @{{ Math.ceil(total / perPage) || 1 }} 页</span>
                            <button
                                type="button"
                                class="secondary-button px-3 py-1 text-xs"
                                :disabled="currentPage >= Math.ceil(total / perPage) || isLoading"
                                @click="changePage(currentPage + 1)"
                            >
                                下一页
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 编辑链接模态框 -->
                <x-admin::modal ref="editLinkModal">
                    <x-slot:header>
                        <p class="text-lg font-semibold">修改 1688 采集链接</p>
                    </x-slot:header>

                    <x-slot:content>
                        <div class="flex flex-col gap-3">
                            <p class="text-xs text-gray-500">
                                修改该任务关联的 1688 商品源链接。保存后可点击“再次采集”以此链接更新商品。
                            </p>
                            <div>
                                <label class="block text-sm font-medium mb-1">1688 商品链接</label>
                                <input
                                    type="text"
                                    v-model="editForm.url"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 dark:border-cherry-700 dark:bg-cherry-800 dark:text-white"
                                    placeholder="https://detail.1688.com/offer/xxxxxx.html"
                                />
                            </div>
                        </div>
                    </x-slot:content>

                    <x-slot:footer>
                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                class="secondary-button"
                                @click="$refs.editLinkModal.close()"
                            >
                                取消
                            </button>
                            <button
                                type="button"
                                class="primary-button"
                                :disabled="editForm.saving || !editForm.url.trim()"
                                @click="saveEditLink"
                            >
                                <img v-if="editForm.saving" class="h-4 w-4 animate-spin inline-block mr-1" src="{{ unopim_asset('images/spinner.svg') }}" />
                                <span>@{{ editForm.saving ? '保存中…' : '确认修改' }}</span>
                            </button>
                        </div>
                    </x-slot:footer>
                </x-admin::modal>

                <!-- 删除确认模态框 -->
                <x-admin::modal ref="deleteConfirmModal">
                    <x-slot:header>
                        <p class="text-lg font-semibold text-rose-600 flex items-center gap-1.5">
                            <span class="icon-cancel text-xl"></span>
                            <span>确认删除采集任务？</span>
                        </p>
                    </x-slot:header>

                    <x-slot:content>
                        <div class="flex flex-col gap-2.5 text-sm text-gray-600 dark:text-gray-300">
                            <p>
                                确定要删除该条采集任务（Offer ID: <strong class="font-mono" v-text="deleteTarget ? deleteTarget.offer_id : ''"></strong>）吗？
                            </p>
                            <div class="rounded bg-rose-50 p-3 text-xs text-rose-700 dark:bg-rose-950/40 dark:text-rose-300">
                                <strong>注意：</strong>
                                删除操作将同时<strong>彻底清空本地缓存的 HTML 快照及下载的媒体图片/视频文件</strong>。此操作不可撤销。
                            </div>
                        </div>
                    </x-slot:content>

                    <x-slot:footer>
                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                class="secondary-button"
                                @click="$refs.deleteConfirmModal.close()"
                            >
                                取消
                            </button>
                            <button
                                type="button"
                                class="primary-button bg-rose-600 hover:bg-rose-700 border-rose-600 text-white"
                                :disabled="isDeleting"
                                @click="confirmDelete"
                            >
                                <img v-if="isDeleting" class="h-4 w-4 animate-spin inline-block mr-1" src="{{ unopim_asset('images/spinner.svg') }}" />
                                <span>@{{ isDeleting ? '正在删除…' : '确认彻底删除' }}</span>
                            </button>
                        </div>
                    </x-slot:footer>
                </x-admin::modal>
            </div>
        </script>

        <script type="module">
            app.component('v-collection-1688-manager', {
                template: '#v-collection-1688-manager-template',

                data() {
                    return {
                        inputUrl: '',
                        isSubmitting: false,
                        submitFeedback: null,

                        records: [],
                        total: 0,
                        currentPage: 1,
                        perPage: 15,
                        filterStatus: '',
                        searchTerm: '',
                        isLoading: false,

                        pollTimer: null,
                        isPolling: false,

                        editForm: {
                            id: null,
                            url: '',
                            saving: false,
                        },

                        deleteTarget: null,
                        isDeleting: false,
                    };
                },

                mounted() {
                    this.loadRecords();
                },

                beforeUnmount() {
                    this.stopPolling();
                },

                methods: {
                    async loadRecords(showLoading = true) {
                        if (showLoading) {
                            this.isLoading = true;
                        }

                        try {
                            const params = new URLSearchParams({
                                page: this.currentPage,
                                limit: this.perPage,
                            });
                            if (this.filterStatus) {
                                params.append('status', this.filterStatus);
                            }
                            if (this.searchTerm.trim()) {
                                params.append('search', this.searchTerm.trim());
                            }

                            const res = await this.$axios.get(`{{ route('admin.collection.1688.index') }}?${params.toString()}`);
                            if (res.data && res.data.success) {
                                this.records = res.data.records || [];
                                this.total = res.data.total || 0;
                                this.checkPollingNeeded();
                            } else {
                                this.records = [];
                                this.total = 0;
                            }
                        } catch (e) {
                            const msg = e.response?.data?.message || '获取采集列表失败';
                            this.$emitter?.emit('add-flash', { type: 'error', message: msg });
                        } finally {
                            this.isLoading = false;
                        }
                    },

                    async startCollection() {
                        const url = this.inputUrl.trim();
                        if (!url) return;

                        this.isSubmitting = true;
                        this.submitFeedback = null;

                        try {
                            const res = await this.$axios.post(`{{ route('admin.collection.1688.store') }}`, { url });
                            if (res.data && res.data.success) {
                                this.submitFeedback = { success: true, message: res.data.message || '采集任务已提交，后台启动抓取中...' };
                                this.$emitter?.emit('add-flash', { type: 'success', message: '1688 采集任务已提交！' });
                                this.inputUrl = '';
                                this.currentPage = 1;
                                await this.loadRecords(false);
                                this.startPolling();
                            } else {
                                const msg = res.data?.message || '提交失败';
                                this.submitFeedback = { success: false, message: msg };
                                this.$emitter?.emit('add-flash', { type: 'error', message: msg });
                            }
                        } catch (e) {
                            const msg = e.response?.data?.message || '无法连接到采集服务';
                            this.submitFeedback = { success: false, message: msg };
                            this.$emitter?.emit('add-flash', { type: 'error', message: msg });
                        } finally {
                            this.isSubmitting = false;
                        }
                    },

                    async handleRetry(record) {
                        record._retrying = true;
                        try {
                            const res = await this.$axios.post(`{{ url(config('app.admin_url') . '/collection/1688') }}/${record.id}/retry`);
                            if (res.data && res.data.success) {
                                this.$emitter?.emit('add-flash', { type: 'success', message: '已重新发起采集任务！' });
                                await this.loadRecords(false);
                                this.startPolling();
                            } else {
                                const msg = res.data?.message || '重新采集失败';
                                this.$emitter?.emit('add-flash', { type: 'error', message: msg });
                            }
                        } catch (e) {
                            const msg = e.response?.data?.message || '重新采集请求失败';
                            this.$emitter?.emit('add-flash', { type: 'error', message: msg });
                        } finally {
                            record._retrying = false;
                        }
                    },

                    openEditModal(record) {
                        this.editForm = {
                            id: record.id,
                            url: record.source_url || '',
                            saving: false,
                        };
                        this.$refs.editLinkModal.open();
                    },

                    async saveEditLink() {
                        if (!this.editForm.url.trim()) return;

                        this.editForm.saving = true;
                        try {
                            const res = await this.$axios.put(`{{ url(config('app.admin_url') . '/collection/1688') }}/${this.editForm.id}`, {
                                source_url: this.editForm.url.trim(),
                            });
                            if (res.data && res.data.success) {
                                this.$emitter?.emit('add-flash', { type: 'success', message: '采集链接已成功修改！' });
                                this.$refs.editLinkModal.close();
                                await this.loadRecords(false);
                            } else {
                                const msg = res.data?.message || '修改失败';
                                this.$emitter?.emit('add-flash', { type: 'error', message: msg });
                            }
                        } catch (e) {
                            const msg = e.response?.data?.message || '修改链接失败';
                            this.$emitter?.emit('add-flash', { type: 'error', message: msg });
                        } finally {
                            this.editForm.saving = false;
                        }
                    },

                    openDeleteModal(record) {
                        this.deleteTarget = record;
                        this.$refs.deleteConfirmModal.open();
                    },

                    async confirmDelete() {
                        if (!this.deleteTarget) return;

                        this.isDeleting = true;
                        try {
                            const res = await this.$axios.delete(`{{ url(config('app.admin_url') . '/collection/1688') }}/${this.deleteTarget.id}`);
                            if (res.data && res.data.success) {
                                this.$emitter?.emit('add-flash', { type: 'success', message: '任务及本地采集文件已彻底删除清空！' });
                                this.$refs.deleteConfirmModal.close();
                                this.deleteTarget = null;
                                await this.loadRecords(false);
                            } else {
                                const msg = res.data?.message || '删除失败';
                                this.$emitter?.emit('add-flash', { type: 'error', message: msg });
                            }
                        } catch (e) {
                            const msg = e.response?.data?.message || '删除任务失败';
                            this.$emitter?.emit('add-flash', { type: 'error', message: msg });
                        } finally {
                            this.isDeleting = false;
                        }
                    },

                    handleFilterChange() {
                        this.currentPage = 1;
                        this.loadRecords();
                    },

                    handleSearch() {
                        this.currentPage = 1;
                        this.loadRecords();
                    },

                    clearSearch() {
                        this.searchTerm = '';
                        this.currentPage = 1;
                        this.loadRecords();
                    },

                    changePage(page) {
                        this.currentPage = page;
                        this.loadRecords();
                    },

                    isRunningStatus(status) {
                        return ['running', 'capturing', 'parsing', 'syncing_unopim', 'created'].includes(status);
                    },

                    isSuccessStatus(status) {
                        return ['success', 'unopim_synced', 'parsed'].includes(status);
                    },

                    statusLabel(record) {
                        if (record.stage === 'capturing' || record.status === 'capturing') return '正在抓取商品页';
                        if (record.stage === 'parsing' || record.status === 'parsing') return '正在解析商品';
                        if (record.stage === 'syncing_unopim' || record.status === 'syncing_unopim') return '正在导入 UnoPIM';
                        if (record.status === 'created') return '任务已创建';
                        return '执行中';
                    },

                    shortError(err) {
                        if (!err) return '';
                        const clean = String(err).replace(/\r?\n/g, ' ').trim();
                        return clean.length > 20 ? clean.slice(0, 20) + '…' : clean;
                    },

                    formatTime(isoString) {
                        if (!isoString) return '—';
                        try {
                            const d = new Date(isoString);
                            return d.toLocaleString('zh-CN', { hour12: false });
                        } catch {
                            return isoString;
                        }
                    },

                    checkPollingNeeded() {
                        const hasActive = (this.records || []).some(r => this.isRunningStatus(r.status));
                        if (hasActive) {
                            this.startPolling();
                        } else {
                            this.stopPolling();
                        }
                    },

                    startPolling() {
                        if (this.pollTimer) return;
                        this.isPolling = true;
                        this.pollTimer = setInterval(async () => {
                            await this.loadRecords(false);
                        }, 3000);
                    },

                    stopPolling() {
                        if (this.pollTimer) {
                            clearInterval(this.pollTimer);
                            this.pollTimer = null;
                        }
                        this.isPolling = false;
                    },
                },
            });
        </script>
    @endpushOnce
</x-admin::layouts>
