@php
    $editingMapping = $mapping ?? null;
    $editingTaxonomy = $editingMapping?->platformCategory?->taxonomy;
    $componentSuffix = $editingMapping?->id ?: 'new';
    $lazyCascader = $lazyCascader ?? false;
@endphp

<x-category::taxonomy-cascader
    component-id="mapping-standard-{{ $componentSuffix }}"
    type="standard"
    input-name="category_code"
    :selected="old('category_code', $editingMapping?->category?->code ?: '')"
    label="PIM 标准类目"
    required
    :lazy="$lazyCascader"
/>

<x-category::taxonomy-cascader
    component-id="mapping-platform-{{ $componentSuffix }}"
    type="platform"
    input-name="external_id"
    :selected="old('external_id', $editingMapping?->platformCategory?->external_id ?: '')"
    :platform="$editingTaxonomy?->platform ?: 'tiktok'"
    :region="$editingTaxonomy?->region ?: 'MY'"
    label="销售平台类目（TikTok 各地区共用类目树）"
    required
    :lazy="$lazyCascader"
/>
<div class="grid grid-cols-2 gap-2">
    <label class="text-sm">映射类型
        <select class="w-full mt-1 px-3 py-2 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="mapping_type">
            @foreach (['exact' => '精确', 'broader' => '平台类目更宽', 'narrower' => '平台类目更细', 'conditional' => '有条件'] as $value => $label)
                <option value="{{ $value }}" @selected(old('mapping_type', $editingMapping?->mapping_type ?: 'exact') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-sm">状态
        <select class="w-full mt-1 px-3 py-2 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="status">
            @foreach (['confirmed' => '已确认', 'draft' => '待审核', 'rejected' => '已拒绝'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $editingMapping?->status ?: 'confirmed') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
</div>
