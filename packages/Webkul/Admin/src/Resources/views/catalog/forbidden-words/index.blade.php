<x-admin::layouts>
    <x-slot:title>违禁词</x-slot>

    <v-forbidden-word-manager>
        <x-admin::page-header title="违禁词">
            <x-slot:actions>
                <button type="button" class="primary-button">新增违禁词</button>
            </x-slot:actions>
        </x-admin::page-header>

        <x-admin::shimmer.datagrid />
    </v-forbidden-word-manager>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-forbidden-word-manager-template">
            <div>
                <x-admin::page-header title="违禁词">
                    <x-slot:actions>
                        <button type="button" class="primary-button" @click="openCreate">新增违禁词</button>
                    </x-slot:actions>
                </x-admin::page-header>

                <p class="mb-4 text-sm text-gray-500">管理商品上架及 AI 描述优化时需要移除的词汇，英文匹配不区分大小写。</p>

                <x-admin::datagrid src="{{ route('admin.catalog.forbidden_words.index') }}" ref="datagrid">
                    <template #body="{ records, performAction }">
                        <div
                            v-for="record in records"
                            :key="record.id"
                            class="grid grid-cols-5 items-center gap-3 border-b px-4 py-3 text-sm text-gray-700 dark:border-cherry-800 dark:text-gray-300"
                        >
                            <p class="font-medium" v-text="record.term"></p>
                            <p v-html="record.status"></p>
                            <p class="truncate" :title="record.notes" v-text="record.notes || '—'"></p>
                            <p v-html="record.updated_at"></p>
                            <div class="flex justify-end gap-1" @click.stop>
                                <button
                                    v-if="record.actions.find(action => action.index === 'edit')"
                                    type="button"
                                    class="icon-edit cursor-pointer rounded p-1.5 text-2xl hover:bg-primary-100"
                                    title="编辑"
                                    @click="openEdit(record.actions.find(action => action.index === 'edit').url)"
                                ></button>
                                <button
                                    v-if="record.actions.find(action => action.index === 'delete')"
                                    type="button"
                                    class="icon-delete cursor-pointer rounded p-1.5 text-2xl hover:bg-primary-100"
                                    title="删除"
                                    @click="performAction(record.actions.find(action => action.index === 'delete'))"
                                ></button>
                            </div>
                        </div>
                    </template>
                </x-admin::datagrid>

                <x-admin::form v-slot="{ handleSubmit, setErrors }" as="div">
                    <form @submit="handleSubmit($event, params => save(params, setErrors))">
                        <x-admin::modal ref="editor">
                            <x-slot:header>
                                <p class="text-lg font-semibold" v-text="id ? '编辑违禁词' : '新增违禁词'"></p>
                            </x-slot:header>

                            <x-slot:content>
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">违禁词</x-admin::form.control-group.label>
                                    <x-admin::form.control-group.control type="text" name="term" rules="required" v-model="term" />
                                    <x-admin::form.control-group.error control-name="term" />
                                </x-admin::form.control-group>

                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>状态</x-admin::form.control-group.label>
                                    <input type="hidden" name="status" value="0" />
                                    <x-admin::form.control-group.control type="switch" name="status" value="1" v-model="status" />
                                </x-admin::form.control-group>

                                <x-admin::form.control-group class="!mb-0">
                                    <x-admin::form.control-group.label>备注</x-admin::form.control-group.label>
                                    <x-admin::form.control-group.control type="textarea" name="notes" v-model="notes" class="h-24" />
                                    <x-admin::form.control-group.error control-name="notes" />
                                </x-admin::form.control-group>
                            </x-slot:content>

                            <x-slot:footer>
                                <button type="submit" class="primary-button" :disabled="saving">
                                    <span v-text="saving ? '保存中…' : '保存'"></span>
                                </button>
                            </x-slot:footer>
                        </x-admin::modal>
                    </form>
                </x-admin::form>
            </div>
        </script>

        <script type="module">
            app.component('v-forbidden-word-manager', {
                template: '#v-forbidden-word-manager-template',

                data() {
                    return { id: null, term: '', status: true, notes: '', saving: false };
                },

                methods: {
                    openCreate() {
                        Object.assign(this, { id: null, term: '', status: true, notes: '' });
                        this.$refs.editor.toggle();
                    },

                    openEdit(url) {
                        this.$axios.get(url).then(response => {
                            const item = response.data.data;
                            Object.assign(this, {
                                id: item.id,
                                term: item.term,
                                status: Boolean(item.status),
                                notes: item.notes || '',
                            });
                            this.$refs.editor.toggle();
                        });
                    },

                    save(params, setErrors) {
                        this.saving = true;
                        const base = @json(route('admin.catalog.forbidden_words.store'));
                        const update = @json(route('admin.catalog.forbidden_words.update', '__ID__')).replace('__ID__', this.id);
                        const payload = { ...params, status: this.status ? 1 : 0 };

                        if (this.id) payload._method = 'PUT';

                        this.$axios.post(this.id ? update : base, payload)
                            .then(response => {
                                this.$refs.editor.close();
                                this.$refs.datagrid.get();
                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            })
                            .catch(error => {
                                if (error.response?.status === 422) setErrors(error.response.data.errors);
                            })
                            .finally(() => this.saving = false);
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
