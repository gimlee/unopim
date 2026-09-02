<x-admin::layouts>
    <x-slot:title>@lang('category::app.taxonomy.title')</x-slot>

    <x-admin::layouts.page-header :title="trans('category::app.taxonomy.title')" />

    <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">@lang('category::app.taxonomy.subtitle')</p>

    <div class="grid grid-cols-4 gap-3 mb-4 max-lg:grid-cols-2 max-sm:grid-cols-1">
        @foreach ([
            ['label' => '标准类目节点', 'value' => $stats['standard']],
            ['label' => '可分配叶子类目', 'value' => $stats['assignable']],
            ['label' => '1688 类目节点', 'value' => $stats['source1688']],
            ['label' => 'TikTok Shop MY 类目节点', 'value' => $stats['tiktokMy']],
        ] as $stat)
            <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
                <div class="text-sm text-gray-500">{{ $stat['label'] }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white">{{ number_format($stat['value']) }}</div>
            </div>
        @endforeach
    </div>

    <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
        <div class="flex items-center justify-between gap-3 mb-4 max-sm:flex-col max-sm:items-stretch">
            <div>
                <h2 class="text-base font-semibold text-gray-800 dark:text-white">PIM 标准类目</h2>
                <p class="mt-1 text-xs text-gray-500">商品只分配到这里的叶子类目；1688 与 TikTok Shop 类目作为独立参考树维护。</p>
            </div>
            <form method="GET" class="flex gap-2">
                <input class="w-72 max-w-full px-3 py-2 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="search" value="{{ request('search') }}" placeholder="名称、路径或 code">
                <button class="secondary-button" type="submit">搜索</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b dark:border-cherry-700 text-left text-gray-500"><th class="py-2">路径</th><th>Code</th><th>别名</th><th>平台映射</th><th></th></tr></thead>
                <tbody>
                @forelse ($categories as $category)
                    <tr class="border-b dark:border-cherry-800">
                        <td class="py-2.5 text-gray-800 dark:text-white">{{ $category->source_path ?: $category->name }}</td>
                        <td class="font-mono text-xs">{{ $category->code }}</td>
                        <td>{{ $category->aliases_count }}</td>
                        <td>{{ $category->mappings_count }}</td>
                        <td><a class="text-blue-600" href="{{ route('admin.catalog.categories.edit', $category->id) }}">编辑</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-gray-500">没有匹配的标准类目</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-admin::pagination.full :paginator="$categories" />
    </div>
</x-admin::layouts>
