<v-content-policy-revisions></v-content-policy-revisions>

@pushOnce('scripts')
    <script type="text/x-template" id="v-content-policy-revisions-template">
        <x-admin::product.section-drawer
            id="content-policy-revisions"
            title="商品描述优化记录"
            subtitle="逐条对比 AI 优化前后的商品文案"
            icon="icon-information"
        >
            <x-slot:toggle>
                <x-admin::product.section-card
                    id="content-policy-revisions"
                    title="商品描述优化记录"
                    icon="icon-information"
                    summary="查看优化前后内容"
                />
            </x-slot:toggle>

            <x-slot:content>
                <div v-if="! revisions.length" class="rounded border border-dashed p-5 text-center text-sm text-gray-500 dark:border-cherry-700">
                    暂无商品描述优化记录
                </div>

                <div v-else class="space-y-2">
                    <article
                        v-for="(revision, index) in revisions"
                        :key="revision.id"
                        class="rounded border border-gray-200 bg-white p-2.5 dark:border-cherry-700 dark:bg-cherry-900"
                    >
                        <header class="mb-2 flex flex-wrap items-start justify-between gap-2 dark:border-cherry-700">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded bg-primary-50 px-2 py-1 text-sm font-semibold text-primary-700 dark:bg-cherry-800 dark:text-primary-300" v-text="methodLabel(revision.method)"></span>
                                    <strong class="text-base text-gray-800 dark:text-white" v-text="revision.template_title || '默认模板'"></strong>
                                </div>
                                <p v-if="revision.matched_terms?.length" class="mt-1 text-sm text-red-600">
                                    命中：<span v-text="revision.matched_terms.join('、')"></span>
                                </p>
                            </div>

                            <div class="text-right text-sm leading-5 text-gray-500">
                                <p v-text="`${revision.provider || '—'} / ${revision.model || '—'}`"></p>
                                <p v-text="`${revision.locale || ''}${revision.region ? ` · ${revision.region}` : ''} · ${revision.created_at || ''}`"></p>
                            </div>
                        </header>

                        <div class="grid gap-1.5 lg:grid-cols-2">
                            <section class="rounded bg-red-50/60 p-2.5 dark:bg-red-950/20">
                                <h4 class="mb-1 text-base font-semibold text-red-700 dark:text-red-300">优化前（已保存）</h4>
                                <div
                                    v-for="(field, fieldIndex) in changedFields(revision)"
                                    :key="`before-${field.key}`"
                                    :class="fieldIndex ? 'mt-2 border-t pt-2 dark:border-cherry-700' : ''"
                                >
                                    <p class="mb-0.5 text-xs font-semibold text-gray-500" v-text="field.label"></p>
                                    <p class="whitespace-pre-line break-words text-base leading-6 text-gray-700 dark:text-gray-200" v-text="plain(revision.original_content?.[field.key]) || '—'"></p>
                                </div>
                            </section>

                            <section class="rounded bg-green-50/60 p-2.5 dark:bg-green-950/20">
                                <div class="mb-1 flex items-center gap-2">
                                    <h4 class="text-base font-semibold text-green-700 dark:text-green-300">优化后（该次结果）</h4>
                                    <span v-if="index === 0" class="rounded bg-green-100 px-1.5 py-0.5 text-xs text-green-700">最新记录</span>
                                </div>
                                <div
                                    v-for="(field, fieldIndex) in changedFields(revision)"
                                    :key="`after-${field.key}`"
                                    :class="fieldIndex ? 'mt-2 border-t pt-2 dark:border-cherry-700' : ''"
                                >
                                    <p class="mb-0.5 text-xs font-semibold text-gray-500" v-text="field.label"></p>
                                    <p class="whitespace-pre-line break-words text-base leading-6 text-gray-700 dark:text-gray-200" v-text="plain(revision.optimized_content?.[field.key]) || '—'"></p>
                                </div>
                            </section>
                        </div>
                    </article>
                </div>
            </x-slot:content>
        </x-admin::product.section-drawer>
    </script>

    <script type="module">
        app.component('v-content-policy-revisions', {
            template: '#v-content-policy-revisions-template',

            data() {
                return { revisions: @json($revisions->values()) };
            },

            mounted() {
                this.$emitter.on('content-policy:revision-created', this.prependRevision);
            },

            beforeUnmount() {
                this.$emitter.off('content-policy:revision-created', this.prependRevision);
            },

            methods: {
                methodLabel(method) {
                    return {
                        ai: '违禁词 AI 优化',
                        ai_fallback: 'AI 失败后精确替换',
                        ai_description: 'Short Description AI 优化',
                    }[method] || method;
                },

                plain(value) {
                    if (! value) return '';
                    const container = document.createElement('div');
                    container.innerHTML = value;
                    container.querySelectorAll('br').forEach(node => node.replaceWith('\n'));

                    return (container.textContent || container.innerText || '')
                        .replace(/\r/g, '')
                        .replace(/[ \t]+\n/g, '\n')
                        .replace(/\n[ \t]+/g, '\n')
                        .replace(/\n{3,}/g, '\n\n')
                        .trim();
                },

                changedFields(revision) {
                    const definitions = [
                        { key: 'name', label: 'Name' },
                        { key: 'short_description', label: 'Short Description' },
                        { key: 'description', label: 'Description' },
                    ];
                    const changed = definitions.filter(field => (
                        this.plain(revision.original_content?.[field.key])
                        !== this.plain(revision.optimized_content?.[field.key])
                    ));

                    return changed.length ? changed : [definitions[1]];
                },

                prependRevision(result) {
                    this.revisions.unshift({
                        id: result.revision_id,
                        method: result.method,
                        template_id: result.template_id,
                        template_title: result.template_title,
                        matched_terms: result.matched_terms || [],
                        original_content: result.original || {},
                        optimized_content: result.content || {},
                        provider: result.provider,
                        model: result.model,
                        locale: result.locale,
                        region: result.region,
                        created_at: result.created_at,
                    });
                },
            },
        });
    </script>
@endPushOnce
