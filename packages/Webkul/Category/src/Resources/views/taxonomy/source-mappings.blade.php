<x-admin::layouts>
    <x-slot:title>PIM 标准类目 ↔ 1688 类目</x-slot>

    <x-admin::layouts.page-header title="PIM 标准类目 ↔ 1688 类目" />

    <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">1688 来源树与 PIM 标准树的映射。当前初始树为一对一复制，后续即使人工调整标准类目，来源映射仍单独保留。</p>

    <div class="grid grid-cols-3 gap-3 mb-4 max-lg:grid-cols-1">
        @foreach ([
            ['label' => '已确认', 'value' => $stats['confirmed']],
            ['label' => '待审核', 'value' => $stats['draft']],
            ['label' => '已拒绝', 'value' => $stats['rejected']],
        ] as $stat)
            <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
                <div class="text-sm text-gray-500">{{ $stat['label'] }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white">{{ number_format($stat['value']) }}</div>
            </div>
        @endforeach
    </div>

    <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
        <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
            <input class="h-10 min-w-[280px] flex-1 px-3 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="search" value="{{ request('search') }}" placeholder="搜索标准/1688 类目名称、路径或 code">
            <select class="h-10 min-w-[150px] px-3 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="status">
                <option value="">全部状态</option>
                @foreach (['confirmed' => '已确认', 'draft' => '待审核', 'rejected' => '已拒绝'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="primary-button !h-10 !px-4 justify-center" type="submit">筛选</button>
            <a class="secondary-button !h-10 !px-4 justify-center" href="{{ route('admin.catalog.taxonomy.mappings.source.index') }}">重置</a>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b dark:border-cherry-700 text-left text-gray-500"><th class="py-2">PIM 标准类目</th><th>1688 类目</th><th>类型</th><th>状态</th></tr></thead>
                <tbody>
                @forelse ($sourceMappings as $mapping)
                    <tr class="border-b dark:border-cherry-800">
                        <td class="py-2.5">{{ $mapping->category->source_path ?: $mapping->category->name }}<br><span class="font-mono text-xs text-gray-500">{{ $mapping->category->code }}</span></td>
                        <td>{{ $mapping->sourceCategory->source_path ?: $mapping->sourceCategory->name }}<br><span class="font-mono text-xs text-gray-500">{{ $mapping->sourceCategory->code }}</span></td>
                        <td>{{ ['exact' => '精确', 'broader' => '更宽', 'narrower' => '更细', 'conditional' => '有条件'][$mapping->mapping_type] ?? $mapping->mapping_type }}</td>
                        <td>{{ ['confirmed' => '已确认', 'draft' => '待审核', 'rejected' => '已拒绝'][$mapping->status] ?? $mapping->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-gray-500">没有符合筛选条件的 1688 类目映射</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-admin::pagination.full :paginator="$sourceMappings" />
    </div>
</x-admin::layouts>
