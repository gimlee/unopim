@php
    $isEdit = (bool) $category;

    $fieldValues = $category?->additional_data ?? [];

    $showParent = ($showParent ?? false) && count($treeItems ?? []);

    $isEmptyRightSection = $rightCategoryFields?->isEmpty();
@endphp

<div class="flex gap-2.5 mt-3.5 max-xl:flex-wrap">
    <div class="flex flex-col gap-2 flex-1 max-xl:flex-auto">
        {!! view_render_event('unopim.admin.catalog.categories.edit.card.general.before', ['category' => $category]) !!}

        <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
            <p class="mb-4 text-base text-gray-800 dark:text-white font-semibold">
                @lang('admin::app.catalog.categories.edit.general')
            </p>

            <x-admin::form.control-group>
                <x-admin::form.control-group.label class="required">
                    @lang('admin::app.catalog.categories.edit.code')
                </x-admin::form.control-group.label>

                <x-admin::form.control-group.control
                    type="text"
                    name="code"
                    rules="required"
                    :class="$isEdit ? 'cursor-not-allowed' : ''"
                    :disabled="$isEdit && (bool) $category->code"
                    :value="$isEdit ? $category->code : old('code')"
                    v-code
                />

                <x-admin::form.control-group.error control-name="code" />
            </x-admin::form.control-group>

            @if (($parentPicker ?? false) || ($parentLabel ?? null))
                @php
                    $parentText = $parentLabel ?: trans('admin::app.catalog.categories.browse.root-level');

                    $selectedParentId = (string) (old('parent_id') ?? $category?->parent_id ?? $parentCategory?->id ?? '');
                @endphp

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.catalog.categories.edit.select-parent-category')
                    </x-admin::form.control-group.label>

                    @if ($parentPicker ?? false)
                        {{-- The drawer body leaves the DOM when it closes, so the picker cannot carry the value into the submit. --}}
                        <input
                            type="hidden"
                            name="parent_id"
                            ref="parentIdField"
                            value="{{ $selectedParentId }}"
                            data-parent-label="{{ $parentText }}"
                            @change="
                                $refs.parentPathLabel.textContent = $event.target.dataset.parentLabel;
                                $refs.parentPathLabel.title = $event.target.dataset.parentLabel;
                            "
                        >

                        <x-admin::drawer width="480px" ref="parentDrawer">
                            <x-slot:toggle>
                                <div class="flex gap-2.5 items-center justify-between w-full px-3 py-2 border dark:border-cherry-800 rounded-md cursor-pointer hover:border-gray-400">
                                    <span
                                        class="text-sm text-gray-600 dark:text-gray-300 truncate"
                                        ref="parentPathLabel"
                                        title="{{ $parentText }}"
                                    >
                                        {{ $parentText }}
                                    </span>

                                    <span class="icon-chevron-right shrink-0 text-2xl text-gray-400 dark:text-gray-300"></span>
                                </div>
                            </x-slot>

                            <x-slot:header>
                                <p class="text-lg text-gray-800 dark:text-white font-bold">
                                    @lang('admin::app.catalog.categories.edit.select-parent-category')
                                </p>
                            </x-slot>

                            <x-slot:content>
                                <label class="flex gap-2 items-center p-1.5 mb-1.5 border-b dark:border-cherry-800 cursor-pointer select-none">
                                    <input
                                        type="radio"
                                        name="parent_id_picker"
                                        value=""
                                        class="hidden peer"
                                        @checked(! $selectedParentId)
                                        @change="
                                            $refs.parentTree.clearSelection();
                                            $refs.parentIdField.value = '';
                                            $refs.parentPathLabel.textContent = $refs.rootLevelLabel.textContent.trim();
                                            $refs.parentPathLabel.title = $refs.rootLevelLabel.textContent.trim();
                                            $refs.parentDrawer.close();
                                        "
                                    >

                                    <span class="icon-radio-normal text-2xl rounded-md peer-checked:icon-radio-selected peer-checked:text-primary-700"></span>

                                    <span class="text-sm text-gray-600 dark:text-gray-300" ref="rootLevelLabel">
                                        @lang('admin::app.catalog.categories.browse.root-level')
                                    </span>
                                </label>

                                <x-admin::tree.category.view
                                    ref="parentTree"
                                    input-type="radio"
                                    name-field="parent_id_picker"
                                    label-field="name"
                                    value-field="id"
                                    id-field="id"
                                    children-page-size="100"
                                    ::show-search="true"
                                    ::show-toolbar="true"
                                    :current-category="$category?->id"
                                    :expanded-branch="json_encode($branchToParent)"
                                    :items="json_encode($treeItems)"
                                    :value="$selectedParentId"
                                    :fallback-locale="config('app.fallback_locale')"
                                    @select-node="
                                        $refs.parentIdField.value = $event.value;
                                        $refs.parentPathLabel.textContent = $event.path;
                                        $refs.parentPathLabel.title = $event.path;
                                        $refs.parentDrawer.close();
                                    "
                                />
                            </x-slot>
                        </x-admin::drawer>
                    @else
                        <x-admin::form.control-group.control
                            type="text"
                            class="cursor-not-allowed"
                            name="parent_label"
                            disabled
                            :value="$parentText"
                        />
                    @endif
                </x-admin::form.control-group>
            @endif
        </div>

        <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
            <p class="mb-4 text-base text-gray-800 dark:text-white font-semibold">
                @lang('category::app.taxonomy.category-metadata')
            </p>

            <div class="grid grid-cols-2 gap-3 max-sm:grid-cols-1">
                <label class="text-sm text-gray-700 dark:text-gray-200">
                    Taxonomy Type / 类目类型
                    <select name="taxonomy_type" class="w-full mt-1 px-3 py-2 border rounded dark:bg-cherry-800 dark:border-cherry-700">
                        @foreach (['standard' => 'Standard / PIM 标准类目', 'container' => 'Container / 导航节点', 'source' => 'Source / 来源类目', 'platform' => 'Platform / 平台类目', 'legacy' => 'Legacy / 旧类目'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('taxonomy_type', $category?->taxonomy_type ?? 'standard') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-sm text-gray-700 dark:text-gray-200">
                    Status / 状态
                    <select name="taxonomy_status" class="w-full mt-1 px-3 py-2 border rounded dark:bg-cherry-800 dark:border-cherry-700">
                        @foreach (['active' => 'Active / 启用', 'inactive' => 'Inactive / 停用', 'deprecated' => 'Deprecated / 已弃用'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('taxonomy_status', $category?->status ?? 'active') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <input type="hidden" name="is_assignable" value="0">
            <label class="flex items-center gap-2 mt-3 text-sm text-gray-700 dark:text-gray-200">
                <input type="checkbox" name="is_assignable" value="1" @checked((bool) old('is_assignable', $category?->is_assignable ?? false))>
                Assignable leaf / 可分配给商品
            </label>

            <div class="grid grid-cols-2 gap-3 mt-3 max-sm:grid-cols-1">
                <label class="text-sm text-gray-700 dark:text-gray-200">Source Platform / 来源平台<input name="source_platform" value="{{ old('source_platform', $category?->source_platform) }}" class="w-full mt-1 px-3 py-2 border rounded dark:bg-cherry-800 dark:border-cherry-700"></label>
                <label class="text-sm text-gray-700 dark:text-gray-200">Source External ID / 来源 ID<input name="source_external_id" value="{{ old('source_external_id', $category?->source_external_id) }}" class="w-full mt-1 px-3 py-2 border rounded dark:bg-cherry-800 dark:border-cherry-700"></label>
            </div>
            <label class="block mt-3 text-sm text-gray-700 dark:text-gray-200">Source Path / 来源路径<input name="source_path" value="{{ old('source_path', $category?->source_path) }}" class="w-full mt-1 px-3 py-2 border rounded dark:bg-cherry-800 dark:border-cherry-700"></label>
            <label class="block mt-3 text-sm text-gray-700 dark:text-gray-200">Source URL / 来源链接<input type="url" name="source_url" value="{{ old('source_url', $category?->source_url) }}" class="w-full mt-1 px-3 py-2 border rounded dark:bg-cherry-800 dark:border-cherry-700"></label>

            <input type="hidden" name="sync_locked" value="0">
            <label class="flex items-start gap-2 mt-3 text-sm text-gray-700 dark:text-gray-200">
                <input type="checkbox" name="sync_locked" value="1" @checked((bool) old('sync_locked', $category?->sync_locked ?? (bool) $category?->source_platform))>
                <span>
                    Preserve manual edits / 保护人工修改
                    <span class="block text-xs text-gray-500">启用后，刷新来源类目只更新来源路径和链接，不覆盖名称、父级、状态及可分配设置。</span>
                </span>
            </label>

            @php
                $taxonomyAliases = $category
                    ? $category->aliases->pluck('alias')->implode("\n")
                    : '';
                $taxonomyRules = $category
                    ? $category->classificationRules->map(fn ($rule) => [
                        'rule_type' => $rule->rule_type,
                        'field' => $rule->field,
                        'operator' => $rule->operator,
                        'value' => $rule->value,
                        'weight' => (float) $rule->weight,
                        'status' => (bool) $rule->status,
                    ])->values()->toJson(JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                    : '[]';
            @endphp
            <label class="block mt-3 text-sm text-gray-700 dark:text-gray-200">
                Aliases / 别名（每行一个）
                <textarea name="taxonomy_aliases" rows="4" class="w-full mt-1 px-3 py-2 border rounded dark:bg-cherry-800 dark:border-cherry-700">{{ old('taxonomy_aliases', $taxonomyAliases) }}</textarea>
            </label>
            <label class="block mt-3 text-sm text-gray-700 dark:text-gray-200">
                Classification Rules / 分类规则（JSON 数组）
                <textarea name="taxonomy_rules" rows="7" class="w-full mt-1 px-3 py-2 border rounded font-mono text-xs dark:bg-cherry-800 dark:border-cherry-700">{{ old('taxonomy_rules', $taxonomyRules) }}</textarea>
            </label>
        </div>

        {!! view_render_event('unopim.admin.catalog.categories.edit.card.general.after', ['category' => $category]) !!}

        @if (! $leftCategoryFields->isEmpty())
            <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
                <x-admin::categories.dynamic-fields
                    :fields="$leftCategoryFields"
                    :fieldValues="$fieldValues"
                />
            </div>
        @endif
    </div>

    @if (! $isEmptyRightSection || $showParent)
        <div class="flex flex-col gap-2 w-[360px] max-w-full">
            @if ($showParent)
                <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
                    <h2 class="block mb-2.5 text-base text-gray-800 dark:text-white font-medium leading-6">
                        @lang('admin::app.catalog.categories.edit.select-parent-category')
                    </h2>

                    <div class="flex flex-col gap-3 h-[calc(100vh-100px)] overflow-y-auto">
                        <x-admin::tree.category.view
                            input-type="radio"
                            name-field="parent_id"
                            label-field="name"
                            value-field="id"
                            id-field="id"
                            children-page-size="100"
                            ::show-search="true"
                            ::show-toolbar="true"
                            :current-category="$category?->id"
                            :expanded-branch="json_encode($branchToParent)"
                            :items="json_encode($treeItems)"
                            :value="(string) (old('parent_id') ?? $category?->parent_id ?? '')"
                            :fallback-locale="config('app.fallback_locale')"
                        />
                    </div>
                </div>
            @endif

            @if (! $isEmptyRightSection)
                {!! view_render_event('unopim.admin.catalog.categories.edit.card.accordion.settings.before', ['category' => $category]) !!}

                <x-admin::accordion :title="trans('admin::app.catalog.categories.edit.right-section')">

                    <x-slot:content>
                        <x-admin::categories.dynamic-fields
                            :fields="$rightCategoryFields"
                            :fieldValues="$fieldValues"
                        />
                    </x-slot>
                </x-admin::accordion>

                {!! view_render_event('unopim.admin.catalog.categories.edit.card.accordion.settings.after', ['category' => $category]) !!}
            @endif
        </div>
    @endif
</div>
