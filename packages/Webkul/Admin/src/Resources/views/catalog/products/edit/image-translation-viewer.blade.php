<v-product-image-translation-viewer
    :product-id="{{ $productId }}"
    sku="{{ $sku }}"
    get-url="{{ route('admin.catalog.products.image_translations.index', $productId) }}"
    delete-url-template="{{ route('admin.catalog.products.image_translations.destroy', [$productId, '__ID__']) }}"
></v-product-image-translation-viewer>

@pushOnce('scripts')
    <script type="text/x-template" id="v-product-image-translation-viewer-template">
        <div class="box-shadow relative rounded bg-white p-4 dark:bg-cherry-900 border border-gray-100 dark:border-cherry-800">
            <!-- Header -->
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-cherry-800">
                <div class="flex items-center gap-2">
                    <span class="icon-camera text-xl text-purple-600 dark:text-purple-400"></span>
                    <h3 class="text-sm font-bold text-gray-800 dark:text-white">
                        多国翻译图片
                    </h3>
                    <span
                        v-if="translations.length > 0"
                        class="px-1.5 py-0.2 rounded-full text-[10px] bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300 font-semibold"
                    >
                        @{{ translations.length }}
                    </span>
                </div>

                <button
                    type="button"
                    class="text-xs font-semibold text-purple-600 hover:text-purple-700 dark:text-purple-400 inline-flex items-center gap-1 cursor-pointer transition hover:underline"
                    @click="openModal"
                >
                    <span>+ 图片翻译</span>
                </button>
            </div>

            <!-- Content -->
            <div v-if="loading" class="py-6 text-center text-xs text-gray-400">
                <img class="h-4 w-4 animate-spin inline-block mr-1.5" src="{{ unopim_asset('images/spinner.svg') }}" />
                <span>加载翻译图片…</span>
            </div>

            <div v-else-if="translations.length === 0" class="py-8 text-center">
                <div class="w-10 h-10 mx-auto mb-2 rounded-full bg-purple-50 dark:bg-cherry-800 flex items-center justify-center text-purple-500">
                    <span class="icon-camera text-lg"></span>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">暂无翻译图片</p>
                <p class="text-[11px] text-gray-400 mt-1">上架对应国家时，将自动优先选用该国翻译图片</p>
                <button
                    type="button"
                    class="mt-3 secondary-button !h-7 !px-3 text-xs inline-flex items-center gap-1 text-purple-600 border-purple-200 hover:bg-purple-50"
                    @click="openModal"
                >
                    <span>立即翻译图片</span>
                </button>
            </div>

            <div v-else class="mt-3 space-y-3">
                <!-- Country Filter Tabs -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1.5 border-b border-gray-100 dark:border-cherry-800 text-xs">
                    <button
                        v-for="country in availableCountries"
                        :key="country.code"
                        type="button"
                        class="px-2.5 py-1 rounded transition text-[11px] font-medium shrink-0 flex items-center gap-1 cursor-pointer"
                        :class="selectedCountry === country.code
                            ? 'bg-purple-600 text-white font-bold shadow-xs'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-cherry-800 dark:text-gray-300'"
                        @click="selectedCountry = country.code"
                    >
                        <span>@{{ country.name }}</span>
                        <span class="text-[9px] px-1 rounded-full"
                            :class="selectedCountry === country.code ? 'bg-purple-700 text-white' : 'bg-gray-200 dark:bg-cherry-700 text-gray-600 dark:text-gray-300'">
                            @{{ country.count }}
                        </span>
                    </button>
                </div>

                <!-- Images Grid for Selected Country -->
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 max-h-[360px] overflow-y-auto pr-1">
                    <div
                        v-for="item in countryTranslations"
                        :key="item.id"
                        class="group relative rounded border border-gray-200 dark:border-cherry-700 bg-gray-50 dark:bg-cherry-800 overflow-hidden flex flex-col"
                    >
                        <!-- Thumbnail Container -->
                        <div
                            class="aspect-square w-full relative overflow-hidden bg-white dark:bg-cherry-900 cursor-pointer"
                            title="双击放大查看大图"
                            @dblclick="openZoomModal(item.local_url || item.translated_url, `${formatType(item.image_type)} (${(item.locale || '').toUpperCase()})`)"
                        >
                            <img :src="item.local_url || item.translated_url" class="w-full h-full object-contain pointer-events-none" loading="lazy" />

                            <!-- Hover Overlay Actions -->
                            <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition flex flex-col items-center justify-center gap-1.5 p-1">
                                <button
                                    type="button"
                                    class="px-2 py-0.5 rounded bg-white/90 hover:bg-white text-gray-800 text-[10px] font-medium cursor-pointer"
                                    @click.stop="openZoomModal(item.local_url || item.translated_url, `${formatType(item.image_type)} (${(item.locale || '').toUpperCase()})`)"
                                >
                                    放大查看
                                </button>
                                <button
                                    type="button"
                                    class="px-2 py-0.5 rounded bg-rose-600 hover:bg-rose-700 text-white text-[10px] font-medium cursor-pointer"
                                    @click.stop="deleteTranslation(item.id)"
                                >
                                    删除
                                </button>
                            </div>
                        </div>

                        <!-- Tag & Label -->
                        <div class="p-1.5 text-[10px] leading-tight border-t border-gray-100 dark:border-cherry-800 bg-white dark:bg-cherry-900">
                            <div class="font-semibold text-gray-700 dark:text-gray-200 truncate flex items-center justify-between">
                                <span>@{{ formatType(item.image_type) }}</span>
                                <span class="text-purple-600 font-bold uppercase">@{{ item.locale }}</span>
                            </div>
                            <div v-if="item.variant_sku" class="text-[9px] text-gray-400 truncate mt-0.5" :title="item.variant_sku">
                                SKU: @{{ item.variant_sku }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Viewer Zoom Lightbox Modal -->
            <div
                v-if="zoomImage"
                class="image-zoom-lightbox-overlay"
                @click="closeZoomModal"
            >
                <div
                    class="relative max-w-[90vw] max-h-[92vh] flex flex-col items-center bg-white dark:bg-cherry-900 rounded-lg p-2.5 shadow-2xl border border-gray-700"
                    @click.stop
                >
                    <div class="w-full flex items-center justify-between px-3 py-1.5 border-b border-gray-200 dark:border-cherry-700 text-xs text-gray-700 dark:text-gray-200">
                        <span class="font-bold flex items-center gap-1.5">
                            <span class="icon-camera text-purple-600"></span>
                            <span>@{{ zoomTitle || '大图预览' }}</span>
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

    <script type="module">
        app.component('v-product-image-translation-viewer', {
            template: '#v-product-image-translation-viewer-template',

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
                deleteUrlTemplate: {
                    type: String,
                    required: true,
                },
            },

            data() {
                return {
                    loading: false,
                    translations: [],
                    candidateRegions: [],
                    selectedCountry: '',
                    zoomImage: null,
                    zoomTitle: '',
                };
            },

            computed: {
                availableCountries() {
                    const map = {};
                    this.translations.forEach(t => {
                        const reg = t.region || 'OTHER';
                        map[reg] = (map[reg] || 0) + 1;
                    });

                    const regionNames = {
                        TH: '🇹🇭 泰国',
                        MY: '🇲🇾 马来西亚',
                        SG: '🇸🇬 新加坡',
                        VN: '🇻🇳 越南',
                        PH: '🇵🇭 菲律宾',
                        ID: '🇮🇩 印尼',
                    };

                    const list = Object.keys(map).map(code => ({
                        code,
                        name: regionNames[code] || code,
                        count: map[code],
                    }));

                    return list;
                },

                countryTranslations() {
                    if (!this.selectedCountry) return this.translations;
                    return this.translations.filter(t => t.region === this.selectedCountry);
                },
            },

            mounted() {
                this.loadTranslations();
                this.$emitter?.on('image-translations:updated', this.loadTranslations);
                window.addEventListener('keydown', this.handleKeydown);
            },

            beforeUnmount() {
                this.$emitter?.off('image-translations:updated', this.loadTranslations);
                window.removeEventListener('keydown', this.handleKeydown);
            },

            methods: {
                emitFlash(type, message) {
                    this.$emitter?.emit('add-flash', { type, message });
                },

                openModal() {
                    const emitter = this.$emitter || window.app?.config?.globalProperties?.$emitter;
                    emitter?.emit('open-image-translation-modal');
                },

                loadTranslations() {
                    this.loading = true;
                    this.$axios.get(this.getUrl)
                        .then(res => {
                            if (res.data.success) {
                                this.translations = res.data.translations || [];
                                this.candidateRegions = res.data.candidate_regions || [];
                                if (!this.selectedCountry && this.availableCountries.length > 0) {
                                    this.selectedCountry = this.availableCountries[0].code;
                                }
                            }
                        })
                        .catch(err => {
                            // Non-blocking
                        })
                        .finally(() => {
                            this.loading = false;
                        });
                },

                formatType(type) {
                    const map = {
                        main: '商品主图',
                        gallery: '商品图库',
                        detail_gallery: '详情描述图',
                        variant: 'SKU变种图',
                    };
                    return map[type] || type;
                },

                deleteTranslation(id) {
                    if (!confirm('确定要删除此张翻译图片吗？')) return;

                    const url = this.deleteUrlTemplate.replace('__ID__', id);
                    this.$axios.delete(url)
                        .then(res => {
                            if (res.data.success) {
                                this.emitFlash('success', '翻译图片已删除');
                                this.loadTranslations();
                            }
                        })
                        .catch(err => {
                            this.emitFlash('error', err.response?.data?.message || '删除失败');
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

    <style>
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
    </style>
@endPushOnce
