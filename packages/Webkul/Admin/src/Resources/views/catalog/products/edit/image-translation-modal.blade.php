<v-product-image-translation-modal
    :product-id="{{ $productId }}"
    sku="{{ $sku }}"
    get-url="{{ route('admin.catalog.products.image_translations.index', $productId) }}"
    translate-url="{{ route('admin.catalog.products.image_translations.translate', $productId) }}"
    status-url-template="{{ route('admin.catalog.products.image_translations.status', [$productId, '__JOB__']) }}"
    save-url="{{ route('admin.catalog.products.image_translations.save', $productId) }}"
></v-product-image-translation-modal>

@pushOnce('scripts')
    <script type="text/x-template" id="v-product-image-translation-modal-template">
        <div class="image-translation-modal-custom">
            <x-admin::modal ref="imageTranslationModal" type="full" class="image-translation-modal-custom">
                <x-slot:header>
                    <div class="flex items-center justify-between pr-6 flex-wrap gap-2">
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
                                <span class="icon-camera text-xl text-purple-600 dark:text-purple-400"></span>
                                <span>商品图片多语言翻译</span>
                                <span class="text-xs px-2 py-0.5 rounded bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300 font-normal">
                                    SKU: @{{ sku }}
                                </span>
                            </h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                选择商品图片分类与特定图片，指定上架目标国家语言，一键调用 AI 进行多语言图片批量翻译并保存。
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs px-2.5 py-1 rounded bg-purple-50 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300 border border-purple-200 dark:border-purple-800 font-medium">
                                💡 双击任意图片可全屏放大查看高清大图
                            </span>
                        </div>
                    </div>
                </x-slot:header>

                <x-slot:content>
                    <div v-if="isLoadingData" class="py-16 text-center text-sm text-gray-500">
                        <img class="h-6 w-6 animate-spin inline-block mr-2" src="{{ unopim_asset('images/spinner.svg') }}" />
                        <span>正在读取商品各分类图片…</span>
                    </div>

                    <div v-else class="space-y-4">
                        <!-- Step 1: Image Selection -->
                        <div class="bg-gray-50 dark:bg-cherry-800/40 p-3 rounded-lg border border-gray-200 dark:border-cherry-700">
                            <!-- Category Filter Tabs -->
                            <div class="flex items-center justify-between flex-wrap gap-2 pb-2.5 border-b border-gray-200 dark:border-cherry-700">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <button
                                        v-for="tab in categoryTabs"
                                        :key="tab.key"
                                        type="button"
                                        class="px-3 py-1 rounded text-xs font-medium transition cursor-pointer flex items-center gap-1.5"
                                        :class="activeTab === tab.key
                                            ? 'bg-purple-600 text-white font-semibold shadow-sm'
                                            : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200 dark:bg-cherry-900 dark:text-gray-300 dark:border-cherry-700'"
                                        @click="activeTab = tab.key"
                                    >
                                        <span>@{{ tab.name }}</span>
                                        <span class="px-1.5 py-0.2 rounded-full text-[10px]"
                                            :class="activeTab === tab.key ? 'bg-purple-700 text-white' : 'bg-gray-200 text-gray-700 dark:bg-cherry-800 dark:text-gray-300'">
                                            @{{ tab.count }}
                                        </span>
                                    </button>
                                </div>

                                <div class="flex items-center gap-2 text-xs">
                                    <button
                                        type="button"
                                        class="text-purple-600 hover:text-purple-700 dark:text-purple-400 font-medium cursor-pointer"
                                        @click="selectAllFiltered"
                                    >
                                        全选当前分类
                                    </button>
                                    <span class="text-gray-300">|</span>
                                    <button
                                        type="button"
                                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 font-medium cursor-pointer"
                                        @click="deselectAll"
                                    >
                                        清空已选
                                    </button>
                                </div>
                            </div>

                            <!-- Image Cards Grid (80% wide, dense grid) -->
                            <div v-if="filteredImages.length === 0" class="py-10 text-center text-xs text-gray-400">
                                当前分类暂无可翻译图片
                            </div>
                            <div v-else class="image-trans-grid mt-3 max-h-[420px] overflow-y-auto pr-1">
                                <div
                                    v-for="img in filteredImages"
                                    :key="img.id"
                                    class="relative group rounded-md border-2 overflow-hidden bg-white dark:bg-cherry-900 transition-all cursor-pointer select-none"
                                    :class="isSelected(img)
                                        ? 'border-purple-600 ring-2 ring-purple-200 dark:ring-purple-900 shadow-sm'
                                        : 'border-gray-200 dark:border-cherry-700 hover:border-purple-300'"
                                    @click="toggleSelect(img)"
                                    @dblclick.stop="openZoomModal(img.url, img.type_label)"
                                    title="单击勾选，双击放大查看高清大图"
                                >
                                    <!-- Image Thumbnail -->
                                    <div class="aspect-square w-full bg-gray-100 dark:bg-cherry-800 flex items-center justify-center overflow-hidden">
                                        <img :src="img.url" class="object-contain w-full h-full pointer-events-none" loading="lazy" />
                                    </div>

                                    <!-- Checkbox Overlay -->
                                    <div class="absolute top-1.5 left-1.5 z-10">
                                        <input
                                            type="checkbox"
                                            :checked="isSelected(img)"
                                            class="w-4 h-4 rounded text-purple-600 focus:ring-purple-500 cursor-pointer pointer-events-none"
                                        />
                                    </div>

                                    <!-- Zoom Hint Icon on Hover -->
                                    <div
                                        class="absolute top-1.5 right-1.5 z-10 opacity-0 group-hover:opacity-100 transition-opacity bg-black/60 hover:bg-black/80 text-white rounded p-1 cursor-pointer"
                                        title="放大查看高清大图"
                                        @click.stop="openZoomModal(img.url, img.type_label)"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"></path>
                                        </svg>
                                    </div>

                                    <!-- Classification Badge -->
                                    <div class="p-1.5 bg-white/95 dark:bg-cherry-900/95 border-t border-gray-100 dark:border-cherry-800 text-[11px] leading-tight">
                                        <div class="font-semibold truncate text-gray-700 dark:text-gray-200">
                                            @{{ img.type_label }}
                                        </div>
                                        <div v-if="img.variant_sku" class="text-[10px] text-gray-400 truncate" :title="img.variant_sku">
                                            SKU: @{{ img.variant_sku }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Translation Target Configuration Bar -->
                        <div class="flex items-center justify-between flex-wrap gap-3 p-3 bg-purple-50/60 dark:bg-purple-950/30 rounded-lg border border-purple-200 dark:border-purple-900/50">
                            <div class="flex items-center gap-4 flex-wrap text-xs">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-gray-500 dark:text-gray-400 font-medium">源语言:</span>
                                    <span class="px-2 py-1 bg-white dark:bg-cherry-800 rounded border border-gray-200 dark:border-cherry-700 font-semibold text-gray-700 dark:text-gray-200">
                                        中文 (zh)
                                    </span>
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <span class="text-gray-700 dark:text-gray-300 font-bold">目标国家与语言:</span>
                                    <select
                                        v-model="selectedRegionCode"
                                        class="px-2.5 py-1 text-xs rounded border border-purple-300 bg-white dark:bg-cherry-800 dark:text-white font-semibold cursor-pointer focus:ring-purple-500"
                                        @change="onRegionChange"
                                    >
                                        <option v-for="reg in candidateRegions" :key="reg.code" :value="reg.code">
                                            @{{ reg.name }} · @{{ reg.lang_name }}
                                        </option>
                                    </select>
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <span class="text-gray-500 dark:text-gray-400 font-medium">翻译引擎:</span>
                                    <select
                                        v-model="selectedPlatform"
                                        class="px-2 py-1 text-xs rounded border border-gray-200 bg-white dark:bg-cherry-800 dark:text-white cursor-pointer"
                                    >
                                        <option value="aeAi">阿里 AI 翻译 (默认推荐)</option>
                                        <option value="ali">阿里标准翻译</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <span class="text-xs text-gray-600 dark:text-gray-300">
                                    已勾选 <strong class="text-purple-700 dark:text-purple-400">@{{ selectedImages.length }}</strong> 张待翻译图片
                                </span>
                                <span v-if="translationJob" class="text-xs font-medium text-purple-700 dark:text-purple-300" v-text="translationStatusLabel"></span>
                                <button
                                    type="button"
                                    class="primary-button !px-5 !py-1.5 text-xs inline-flex items-center gap-1.5 bg-purple-600 hover:bg-purple-700 border-purple-600 text-white shadow-sm"
                                    :disabled="isTranslating || isSaving || selectedImages.length === 0"
                                    @click="startTranslation"
                                >
                                    <img v-if="isTranslating" class="h-3.5 w-3.5 animate-spin" src="{{ unopim_asset('images/spinner.svg') }}" />
                                    <span v-else class="icon-magic text-base"></span>
                                    <span v-text="isTranslating ? `正在批量翻译 (${selectedImages.length} 张)…` : `开始翻译 (${selectedImages.length} 张)`"></span>
                                </button>
                            </div>
                        </div>

                        <!-- Step 3: Translation Results Preview & Save Section -->
                        <div v-if="translatedResults.length > 0" class="mt-4 p-4 rounded-lg border-2 border-emerald-200 bg-emerald-50/40 dark:bg-emerald-950/20 dark:border-emerald-800">
                            <div class="flex items-center justify-between pb-3 border-b border-emerald-200 dark:border-emerald-800 flex-wrap gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="icon-check text-xl text-emerald-600 dark:text-emerald-400"></span>
                                    <h4 class="text-sm font-bold text-gray-800 dark:text-white">
                                        翻译结果预览 (@{{ translatedResults.length }} 张)
                                    </h4>
                                    <span class="text-xs text-gray-500">
                                        目标语言: <strong class="text-emerald-700 dark:text-emerald-300">@{{ currentTargetLabel }}</strong>
                                    </span>
                                </div>

                                <button
                                    type="button"
                                    class="primary-button !px-5 !py-1.5 text-xs inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 border-emerald-600 text-white shadow-sm"
                                    :disabled="isSaving"
                                    @click="saveTranslationResults"
                                >
                                    <img v-if="isSaving" class="h-3.5 w-3.5 animate-spin" src="{{ unopim_asset('images/spinner.svg') }}" />
                                    <span v-else class="icon-export text-base"></span>
                                    <span v-text="isSaving ? '正在保存入库…' : `保存全部翻译结果 (${translatedResults.length} 张)`"></span>
                                </button>
                            </div>

                            <!-- Side-by-side Cards (expanded columns for 80% width) -->
                            <div class="image-trans-preview-grid mt-3.5 max-h-[400px] overflow-y-auto pr-1">
                                <div
                                    v-for="(res, idx) in translatedResults"
                                    :key="idx"
                                    class="p-2.5 rounded bg-white dark:bg-cherry-900 border border-emerald-100 dark:border-emerald-900 shadow-xs flex flex-col gap-2"
                                >
                                    <div class="flex items-center justify-between text-[11px] font-semibold text-gray-600 dark:text-gray-300">
                                        <span class="truncate">@{{ res.image_type === 'variant' ? `变种: ${res.variant_sku || ''}` : res.image_type }}</span>
                                        <span class="text-emerald-600 dark:text-emerald-400 font-bold">已翻译</span>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <!-- Original -->
                                        <div class="flex flex-col gap-1 items-center">
                                            <div
                                                class="aspect-square w-full rounded border overflow-hidden bg-gray-50 dark:bg-cherry-800 cursor-pointer relative group"
                                                title="双击放大查看原图大图"
                                                @dblclick.stop="openZoomModal(res.original_view, '中文原图')"
                                            >
                                                <img :src="res.original_view" class="w-full h-full object-contain pointer-events-none" />
                                                <div
                                                    class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white text-[10px]"
                                                    @click.stop="openZoomModal(res.original_view, '中文原图')"
                                                >
                                                    双击放大
                                                </div>
                                            </div>
                                            <span class="text-[10px] text-gray-400">中文原图</span>
                                        </div>

                                        <!-- Translated -->
                                        <div class="flex flex-col gap-1 items-center">
                                            <div
                                                class="aspect-square w-full rounded border-2 border-emerald-400 overflow-hidden bg-gray-50 dark:bg-cherry-800 relative group cursor-pointer"
                                                title="双击放大查看译图大图"
                                                @dblclick.stop="openZoomModal(res.translated_url, `${(res.locale || '').toUpperCase()} 译图`)"
                                            >
                                                <img :src="res.translated_url" class="w-full h-full object-contain pointer-events-none" />
                                                <div
                                                    class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white text-[10px]"
                                                    @click.stop="openZoomModal(res.translated_url, `${(res.locale || '').toUpperCase()} 译图`)"
                                                >
                                                    双击放大
                                                </div>
                                            </div>
                                            <span class="text-[10px] text-emerald-600 font-bold">@{{ res.locale }} 译图</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-slot:content>
            </x-admin::modal>

            <!-- Big Image Zoom Lightbox Modal -->
            <div
                v-if="zoomImage"
                class="image-zoom-lightbox-overlay"
                @click="closeZoomModal"
            >
                <div
                    class="relative max-w-[90vw] max-h-[92vh] flex flex-col items-center bg-white dark:bg-cherry-900 rounded-lg p-2.5 shadow-2xl border border-gray-700"
                    @click.stop
                >
                    <!-- Top bar with title and close button -->
                    <div class="w-full flex items-center justify-between px-3 py-1.5 border-b border-gray-200 dark:border-cherry-700 text-xs text-gray-700 dark:text-gray-200">
                        <span class="font-bold flex items-center gap-1.5">
                            <span class="icon-camera text-purple-600"></span>
                            <span>@{{ zoomTitle || '大图高清预览' }}</span>
                        </span>
                        <div class="flex items-center gap-4">
                            <a
                                :href="zoomImage"
                                target="_blank"
                                class="text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center gap-1 text-xs"
                            >
                                在新窗口打开原图 ↗
                            </a>
                            <button
                                type="button"
                                class="text-gray-400 hover:text-gray-700 dark:hover:text-white text-base font-bold leading-none p-1 cursor-pointer"
                                title="按 ESC 或点击背景关闭"
                                @click="closeZoomModal"
                            >
                                ✕
                            </button>
                        </div>
                    </div>

                    <!-- Big Image Display -->
                    <div class="overflow-auto max-h-[82vh] max-w-[88vw] flex items-center justify-center p-2">
                        <img
                            :src="zoomImage"
                            class="max-h-[80vh] max-w-[86vw] object-contain rounded shadow-sm select-none"
                        />
                    </div>
                </div>
            </div>
        </div>
    </script>

    <style>
        .image-translation-modal-custom div[ref="modalContent"],
        .image-translation-modal-custom div.box-shadow {
            width: 80vw !important;
            max-width: 80vw !important;
        }
        .image-trans-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(115px, 1fr)) !important;
            gap: 12px !important;
        }
        .image-trans-preview-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)) !important;
            gap: 14px !important;
        }
        .image-zoom-lightbox-overlay {
            position: fixed !important;
            inset: 0 !important;
            z-index: 999999 !important;
            background-color: rgba(0, 0, 0, 0.85) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 16px !important;
            backdrop-filter: blur(4px) !important;
        }
        @media (max-width: 768px) {
            .image-translation-modal-custom div[ref="modalContent"],
            .image-translation-modal-custom div.box-shadow {
                width: 95vw !important;
                max-width: 95vw !important;
            }
            .image-trans-grid {
                grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)) !important;
                gap: 8px !important;
            }
        }
    </style>

    <script type="module">
        app.component('v-product-image-translation-modal', {
            template: '#v-product-image-translation-modal-template',

            props: {
                productId: {
                    type: Number,
                    required: true,
                },
                sku: {
                    type: String,
                    required: true,
                },
                getUrl: {
                    type: String,
                    required: true,
                },
                translateUrl: {
                    type: String,
                    required: true,
                },
                saveUrl: {
                    type: String,
                    required: true,
                },
                statusUrlTemplate: {
                    type: String,
                    required: true,
                },
            },

            data() {
                return {
                    isLoadingData: false,
                    isTranslating: false,
                    isSaving: false,
                    allImages: [],
                    existingTranslations: [],
                    candidateRegions: [
                        { code: 'TH', name: '泰国', lang: 'th', lang_name: '泰文 (Thai)' },
                        { code: 'MY', name: '马来西亚', lang: 'ms', lang_name: '马来文 (Malay)' },
                        { code: 'SG', name: '新加坡/国际', lang: 'en', lang_name: '英文 (English)' },
                        { code: 'VN', name: '越南', lang: 'vi', lang_name: '越南文 (Vietnamese)' },
                        { code: 'PH', name: '菲律宾', lang: 'fil', lang_name: '菲律宾文 (Filipino)' },
                        { code: 'ID', name: '印尼', lang: 'id', lang_name: '印尼文 (Indonesian)' },
                    ],
                    activeTab: 'all',
                    selectedImages: [],
                    selectedRegionCode: 'TH',
                    selectedTargetLang: 'th',
                    selectedPlatform: 'aeAi',
                    translatedResults: [],
                    translationJob: null,
                    translationPollTimer: null,
                    zoomImage: null,
                    zoomTitle: '',
                };
            },

            computed: {
                categoryTabs() {
                    const galleryCount = this.allImages.filter(i => i.type === 'main' || i.type === 'gallery').length;
                    const detailCount = this.allImages.filter(i => i.type === 'detail_gallery').length;
                    const variantCount = this.allImages.filter(i => i.type === 'variant').length;

                    return [
                        { key: 'all', name: '全部图片', count: this.allImages.length },
                        { key: 'gallery', name: '主图/轮播图', count: galleryCount },
                        { key: 'detail', name: '详情描述图', count: detailCount },
                        { key: 'variant', name: 'SKU变种图', count: variantCount },
                    ];
                },

                filteredImages() {
                    if (this.activeTab === 'gallery') {
                        return this.allImages.filter(i => i.type === 'main' || i.type === 'gallery');
                    }
                    if (this.activeTab === 'detail') {
                        return this.allImages.filter(i => i.type === 'detail_gallery');
                    }
                    if (this.activeTab === 'variant') {
                        return this.allImages.filter(i => i.type === 'variant');
                    }
                    return this.allImages;
                },

                currentTargetLabel() {
                    const reg = this.candidateRegions.find(r => r.code === this.selectedRegionCode);
                    return reg ? `${reg.name} · ${reg.lang_name}` : this.selectedTargetLang;
                },

                translationStatusLabel() {
                    const labels = {
                        queued: '翻译任务排队中…',
                        running: '翻译任务执行中…',
                        completed: '翻译任务已完成',
                        failed: '翻译任务失败',
                    };

                    return labels[this.translationJob?.status] || '';
                },
            },

            mounted() {
                this.$emitter?.on('open-image-translation-modal', this.openModal);
                window.addEventListener('keydown', this.handleKeydown);
            },

            beforeUnmount() {
                clearTimeout(this.translationPollTimer);
                this.$emitter?.off('open-image-translation-modal', this.openModal);
                window.removeEventListener('keydown', this.handleKeydown);
            },

            methods: {
                emitFlash(type, message) {
                    this.$emitter?.emit('add-flash', { type, message });
                },

                openModal() {
                    this.translatedResults = [];
                    this.selectedImages = [];
                    this.$refs.imageTranslationModal.open();
                    this.fetchImages();
                },

                fetchImages() {
                    this.isLoadingData = true;
                    this.$axios.get(this.getUrl)
                        .then(res => {
                            if (res.data.success) {
                                this.allImages = res.data.images || [];
                                this.existingTranslations = res.data.translations || [];
                                if (res.data.candidate_regions) {
                                    this.candidateRegions = res.data.candidate_regions;
                                }
                                if (res.data.active_job) {
                                    this.translationJob = res.data.active_job;
                                    this.isTranslating = true;
                                    this.pollTranslationStatus();
                                }
                                // Default select all images
                                this.selectedImages = [...this.allImages];
                            }
                        })
                        .catch(err => {
                            this.emitFlash('error', err.response?.data?.message || '加载商品图片失败');
                        })
                        .finally(() => {
                            this.isLoadingData = false;
                        });
                },

                onRegionChange() {
                    const reg = this.candidateRegions.find(r => r.code === this.selectedRegionCode);
                    if (reg) {
                        this.selectedTargetLang = reg.lang;
                    }
                },

                isSelected(img) {
                    return this.selectedImages.some(i => i.id === img.id);
                },

                toggleSelect(img) {
                    const idx = this.selectedImages.findIndex(i => i.id === img.id);
                    if (idx >= 0) {
                        this.selectedImages.splice(idx, 1);
                    } else {
                        this.selectedImages.push(img);
                    }
                },

                selectAllFiltered() {
                    const currentFiltered = this.filteredImages;
                    currentFiltered.forEach(img => {
                        if (!this.isSelected(img)) {
                            this.selectedImages.push(img);
                        }
                    });
                },

                deselectAllFiltered() {
                    const filteredIds = new Set(this.filteredImages.map(i => i.id));
                    this.selectedImages = this.selectedImages.filter(i => !filteredIds.has(i.id));
                },

                deselectAll() {
                    this.selectedImages = [];
                },

                startTranslation() {
                    if (this.selectedImages.length === 0) {
                        this.emitFlash('warning', '请至少勾选一张待翻译图片');
                        return;
                    }

                    this.isTranslating = true;
                    this.translatedResults = [];
                    clearTimeout(this.translationPollTimer);

                    this.$axios.post(this.translateUrl, {
                        images: this.selectedImages,
                        region: this.selectedRegionCode,
                        target_lang: this.selectedTargetLang,
                        source_lang: 'zh',
                        platform: this.selectedPlatform,
                    })
                    .then(res => {
                        if (res.data.success && res.data.job_id) {
                            this.translationJob = {
                                id: res.data.job_id,
                                status: res.data.status || 'queued',
                            };
                            this.emitFlash('success', res.data.message || '图片翻译任务已进入队列');
                            this.pollTranslationStatus();
                        } else {
                            this.emitFlash('error', res.data.message || '没有图片翻译成功');
                        }
                    })
                    .catch(err => {
                        const msg = err.response?.data?.message || '图片翻译调用失败';
                        this.emitFlash('error', msg);
                        this.isTranslating = false;
                    });
                },

                pollTranslationStatus() {
                    if (! this.translationJob?.id) return;

                    clearTimeout(this.translationPollTimer);
                    const url = this.statusUrlTemplate.replace('__JOB__', this.translationJob.id);

                    this.$axios.get(url)
                        .then(res => {
                            const job = res.data.data;
                            this.translationJob = job;

                            if (job.status === 'completed') {
                                this.translatedResults = job.results || [];
                                this.isTranslating = false;
                                this.emitFlash('success', `图片翻译完成，已生成 ${this.translatedResults.length} 张译图`);
                                return;
                            }

                            if (job.status === 'failed') {
                                this.isTranslating = false;
                                this.emitFlash('error', job.error || '图片翻译任务失败');
                                return;
                            }

                            this.translationPollTimer = setTimeout(() => this.pollTranslationStatus(), 2000);
                        })
                        .catch(() => {
                            this.translationPollTimer = setTimeout(() => this.pollTranslationStatus(), 5000);
                        });
                },

                saveTranslationResults() {
                    if (this.translatedResults.length === 0) return;

                    this.isSaving = true;
                    this.$axios.post(this.saveUrl, {
                        translations: this.translatedResults,
                    })
                    .then(res => {
                        if (res.data.success) {
                            this.emitFlash('success', res.data.message || '翻译图片已成功保存！');
                            this.$emitter?.emit('image-translations:updated');
                            setTimeout(() => {
                                this.$refs.imageTranslationModal.close();
                            }, 500);
                        }
                    })
                    .catch(err => {
                        this.emitFlash('error', err.response?.data?.message || '保存翻译图片失败');
                    })
                    .finally(() => {
                        this.isSaving = false;
                    });
                },

                openZoomModal(url, title = '') {
                    if (!url) return;
                    this.zoomImage = url;
                    this.zoomTitle = title;
                },

                closeZoomModal() {
                    this.zoomImage = null;
                    this.zoomTitle = '';
                },

                handleKeydown(e) {
                    if (e.key === 'Escape' && this.zoomImage) {
                        this.closeZoomModal();
                    }
                },
            },
        });
    </script>
@endPushOnce
