<x-admin::layouts>
    <x-slot:title>@lang('category::app.taxonomy.title')</x-slot>

    <x-admin::layouts.page-header :title="trans('category::app.taxonomy.title')" />

    <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">@lang('category::app.taxonomy.subtitle')</p>

    <div class="grid grid-cols-4 gap-3 mb-4 max-lg:grid-cols-2 max-sm:grid-cols-1">
        @foreach ([
            ['label' => trans('category::app.taxonomy.standard-count'), 'value' => $stats['standard']],
            ['label' => trans('category::app.taxonomy.assignable-count'), 'value' => $stats['assignable']],
            ['label' => trans('category::app.taxonomy.mapping-count'), 'value' => $stats['mappings']],
            ['label' => trans('category::app.taxonomy.review-count'), 'value' => $stats['reviews']],
        ] as $stat)
            <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
                <div class="text-sm text-gray-500">{{ $stat['label'] }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white">{{ number_format($stat['value']) }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-[minmax(0,1fr)_400px] gap-4 max-xl:grid-cols-1">
        <div class="flex flex-col gap-4">
            <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
                <div class="flex items-center justify-between gap-3 mb-4 max-sm:flex-col max-sm:items-stretch">
                    <h2 class="text-base font-semibold text-gray-800 dark:text-white">PIM 标准类目</h2>
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
                <div class="mt-4">{{ $categories->links() }}</div>
            </div>

            <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
                <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-white">平台类目映射</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="border-b dark:border-cherry-700 text-left text-gray-500"><th class="py-2">标准类目</th><th>平台/地区</th><th>平台路径</th><th>外部 ID</th><th>状态</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($mappings as $mapping)
                            <tr class="border-b dark:border-cherry-800">
                                <td class="py-2.5">{{ $mapping->category->source_path ?: $mapping->category->name }}</td>
                                <td>{{ strtoupper($mapping->platformCategory->taxonomy->platform) }} / {{ $mapping->platformCategory->taxonomy->region ?: 'GLOBAL' }}</td>
                                <td>{{ $mapping->platformCategory->path }}</td>
                                <td class="font-mono text-xs">{{ $mapping->platformCategory->external_id }}</td>
                                <td>{{ $mapping->status }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.catalog.taxonomy.mappings.delete', $mapping->id) }}" onsubmit="return confirm('删除这个映射？')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600" type="submit">删除</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-gray-500">尚未配置平台类目映射</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
                <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-white">待审核商品主类目</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="border-b dark:border-cherry-700 text-left text-gray-500"><th class="py-2">SKU</th><th>建议类目</th><th>方法</th><th>置信度</th><th>操作</th></tr></thead>
                        <tbody>
                        @forelse ($assignments as $assignment)
                            <tr class="border-b dark:border-cherry-800">
                                <td class="py-2.5"><a class="text-blue-600" href="{{ route('admin.catalog.products.edit', $assignment->product_id) }}">{{ $assignment->product->sku }}</a></td>
                                <td>{{ $assignment->category->source_path ?: $assignment->category->name }}</td>
                                <td>{{ $assignment->method }}</td>
                                <td>{{ $assignment->confidence === null ? '-' : number_format((float) $assignment->confidence * 100, 0).'%' }}</td>
                                <td class="flex gap-2 py-2">
                                    @foreach (['confirmed' => '确认', 'rejected' => '拒绝'] as $status => $label)
                                        <form method="POST" action="{{ route('admin.catalog.taxonomy.assignments.review', $assignment->id) }}">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="status" value="{{ $status }}">
                                            <button class="{{ $status === 'confirmed' ? 'text-green-600' : 'text-red-600' }}" type="submit">{{ $label }}</button>
                                        </form>
                                    @endforeach
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-gray-500">没有待审核商品</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
                <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-white">新增 / 更新平台映射</h2>
                <form method="POST" action="{{ route('admin.catalog.taxonomy.mappings.store') }}" class="flex flex-col gap-3">
                    @csrf
                    <label class="text-sm">标准类目 code<input class="w-full mt-1 px-3 py-2 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="category_code" required></label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="text-sm">平台<input class="w-full mt-1 px-3 py-2 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="platform" value="tiktok" required></label>
                        <label class="text-sm">地区<input class="w-full mt-1 px-3 py-2 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="region" value="MY" required></label>
                    </div>
                    <label class="text-sm">平台类目 ID<input class="w-full mt-1 px-3 py-2 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="external_id" required></label>
                    <label class="text-sm">平台类目名称<input class="w-full mt-1 px-3 py-2 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="name" required></label>
                    <label class="text-sm">完整路径<textarea class="w-full mt-1 px-3 py-2 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="path" rows="3" required></textarea></label>
                    <label class="text-sm">类目版本<input class="w-full mt-1 px-3 py-2 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="version" value="current"></label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="text-sm">映射类型<select class="w-full mt-1 px-3 py-2 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="mapping_type"><option value="exact">exact</option><option value="broader">broader</option><option value="narrower">narrower</option><option value="conditional">conditional</option></select></label>
                        <label class="text-sm">状态<select class="w-full mt-1 px-3 py-2 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="status"><option value="confirmed">confirmed</option><option value="draft">draft</option><option value="rejected">rejected</option></select></label>
                    </div>
                    <button class="primary-button justify-center" type="submit">保存映射</button>
                </form>
            </div>

            <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
                <h2 class="mb-3 text-base font-semibold text-gray-800 dark:text-white">平台类目版本</h2>
                @forelse ($taxonomies as $taxonomy)
                    <div class="flex justify-between py-2 border-b dark:border-cherry-800 text-sm">
                        <span>{{ strtoupper($taxonomy->platform) }} / {{ $taxonomy->region ?: 'GLOBAL' }}<br><span class="text-xs text-gray-500">{{ $taxonomy->version }}</span></span>
                        <span>{{ $taxonomy->categories_count }} 类目<br><span class="text-xs text-gray-500">{{ $taxonomy->status }}</span></span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">尚未同步平台类目</p>
                @endforelse
            </div>
        </div>
    </div>
</x-admin::layouts>
