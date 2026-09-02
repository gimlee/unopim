<x-admin::layouts>
    <x-slot:title>PIM 标准类目 ↔ 销售平台类目</x-slot>

    <x-admin::layouts.page-header title="PIM 标准类目 ↔ 销售平台类目" />

    <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">默认展示所有状态，包括已拒绝映射。待审核映射可以直接确认、拒绝，或在弹窗中修改标准类目、目标平台类目、映射类型和状态。</p>

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-700">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="grid grid-cols-3 gap-3 mb-4 max-lg:grid-cols-1">
        @foreach ([
            ['label' => '已确认', 'value' => $stats['confirmed'], 'status' => 'confirmed'],
            ['label' => '待审核', 'value' => $stats['draft'], 'status' => 'draft'],
            ['label' => '已拒绝', 'value' => $stats['rejected'], 'status' => 'rejected'],
        ] as $stat)
            <a class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow" href="{{ route('admin.catalog.taxonomy.mappings.platform.index', ['status' => $stat['status']]) }}">
                <div class="text-sm text-gray-500">{{ $stat['label'] }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white">{{ number_format($stat['value']) }}</div>
            </a>
        @endforeach
    </div>

    <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
        <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
            <input class="h-10 min-w-[260px] flex-1 px-3 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="search" value="{{ request('search') }}" placeholder="类目名称、路径、code 或平台 ID">
            <select class="h-10 min-w-[150px] px-3 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="status">
                <option value="">全部状态（含已拒绝）</option>
                @foreach (['confirmed' => '已确认', 'draft' => '待审核', 'rejected' => '已拒绝'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select class="h-10 min-w-[130px] px-3 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="platform">
                <option value="">全部平台</option>
                @foreach ($platforms as $value)
                    <option value="{{ $value }}" @selected(request('platform') === $value)>{{ strtoupper($value) }}</option>
                @endforeach
            </select>
            <select class="h-10 min-w-[120px] px-3 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="region">
                <option value="">全部地区</option>
                @foreach ($regions as $value)
                    <option value="{{ $value }}" @selected(strtoupper((string) request('region')) === $value)>{{ $value }}</option>
                @endforeach
            </select>
            <select class="h-10 min-w-[150px] px-3 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="mapping_type">
                <option value="">全部映射类型</option>
                @foreach (['exact' => '精确', 'broader' => '平台类目更宽', 'narrower' => '平台类目更细', 'conditional' => '有条件'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('mapping_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select class="h-10 min-w-[170px] px-3 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="sort">
                <option value="confidence_desc" @selected(request('sort', 'confidence_desc') === 'confidence_desc')>置信度：高到低</option>
                <option value="confidence_asc" @selected(request('sort') === 'confidence_asc')>置信度：低到高</option>
                <option value="latest" @selected(request('sort') === 'latest')>最近更新</option>
            </select>
            <button class="primary-button !h-10 !px-4 justify-center" type="submit">筛选</button>
            <a class="secondary-button !h-10 !px-4 justify-center" href="{{ route('admin.catalog.taxonomy.mappings.platform.index') }}">重置</a>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b dark:border-cherry-700 text-left text-gray-500"><th class="py-2">PIM 标准类目</th><th>平台/地区</th><th>销售平台类目</th><th>类型</th><th>状态</th><th>操作</th></tr></thead>
                <tbody>
                @forelse ($mappings as $mapping)
                    <tr class="border-b dark:border-cherry-800">
                        <td class="py-2.5">{{ $mapping->category->source_path ?: $mapping->category->name }}<br><span class="font-mono text-xs text-gray-500">{{ $mapping->category->code }}</span></td>
                        <td>{{ strtoupper($mapping->platformCategory->taxonomy->platform) }} / {{ $mapping->platformCategory->taxonomy->region ?: 'GLOBAL' }}</td>
                        <td>{{ $mapping->platformCategory->path }}<br><span class="font-mono text-xs text-gray-500">{{ $mapping->platformCategory->external_id }}</span></td>
                        <td>{{ ['exact' => '精确', 'broader' => '更宽', 'narrower' => '更细', 'conditional' => '有条件'][$mapping->mapping_type] ?? $mapping->mapping_type }}</td>
                        <td>{{ ['confirmed' => '已确认', 'draft' => '待审核', 'rejected' => '已拒绝'][$mapping->status] ?? $mapping->status }} @if ($mapping->confidence !== null)<br><span class="text-xs text-gray-500">{{ number_format((float) $mapping->confidence * 100, 0) }}%</span>@endif</td>
                        <td class="whitespace-nowrap">
                            <button class="mr-2 text-blue-600" type="button" onclick="document.getElementById('mapping-dialog-{{ $mapping->id }}').showModal(); window.dispatchEvent(new CustomEvent('taxonomy-cascader:open', { detail: { componentIds: ['mapping-standard-{{ $mapping->id }}', 'mapping-platform-{{ $mapping->id }}'] } }))">编辑</button>
                            @if ($mapping->status === 'draft')
                                @foreach (['confirmed' => '确认', 'rejected' => '拒绝'] as $status => $label)
                                    <form class="inline mr-2" method="POST" action="{{ route('admin.catalog.taxonomy.mappings.review', $mapping->id) }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="status" value="{{ $status }}">
                                        <button class="{{ $status === 'confirmed' ? 'text-green-600' : 'text-orange-600' }}" type="submit">{{ $label }}</button>
                                    </form>
                                @endforeach
                            @elseif ($mapping->status === 'rejected')
                                <form class="inline mr-2" method="POST" action="{{ route('admin.catalog.taxonomy.mappings.review', $mapping->id) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="status" value="draft">
                                    <button class="text-orange-600" type="submit">重新审核</button>
                                </form>
                            @endif
                            <form class="inline" method="POST" action="{{ route('admin.catalog.taxonomy.mappings.delete', $mapping->id) }}" onsubmit="return confirm('永久删除这个映射？')">
                                @csrf @method('DELETE')
                                <button class="text-red-600" type="submit">删除</button>
                            </form>

                            <dialog id="mapping-dialog-{{ $mapping->id }}" class="w-[640px] max-w-[calc(100vw-2rem)] rounded bg-white p-0 text-left shadow-xl backdrop:bg-black/40 dark:bg-cherry-900 dark:text-white">
                                <div class="flex items-center justify-between border-b p-4 dark:border-cherry-700">
                                    <div>
                                        <h2 class="text-base font-semibold">编辑平台类目映射</h2>
                                        <p class="mt-1 text-xs text-gray-500">映射 ID：{{ $mapping->id }}</p>
                                    </div>
                                    <form method="dialog"><button class="text-xl text-gray-500" type="submit" aria-label="关闭">×</button></form>
                                </div>
                                <form method="POST" action="{{ route('admin.catalog.taxonomy.mappings.update', $mapping->id) }}" class="flex flex-col gap-3 p-4">
                                    @csrf @method('PUT')
                                    @include('category::taxonomy._mapping-fields', ['mapping' => $mapping, 'lazyCascader' => true])
                                    <div class="mt-2 flex justify-end gap-2">
                                        <button class="secondary-button" type="button" onclick="document.getElementById('mapping-dialog-{{ $mapping->id }}').close()">取消</button>
                                        <button class="primary-button" type="submit">保存修改</button>
                                    </div>
                                </form>
                            </dialog>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-gray-500">没有符合筛选条件的平台类目映射</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-admin::pagination.full :paginator="$mappings" />
    </div>

    <div class="mt-4 p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
        <h2 class="mb-3 text-base font-semibold text-gray-800 dark:text-white">已同步平台类目版本</h2>
        <div class="grid grid-cols-3 gap-3 max-lg:grid-cols-1">
            @forelse ($taxonomies as $taxonomy)
                <div class="flex justify-between rounded border p-3 text-sm dark:border-cherry-700">
                    <span>{{ strtoupper($taxonomy->platform) }} / {{ $taxonomy->region ?: 'GLOBAL' }}<br><span class="text-xs text-gray-500">{{ $taxonomy->version }}</span></span>
                    <span class="text-right">{{ $taxonomy->categories_count }} 类目<br><span class="text-xs text-gray-500">{{ $taxonomy->status }}</span></span>
                </div>
            @empty
                <p class="text-sm text-gray-500">尚未同步平台类目</p>
            @endforelse
        </div>
    </div>
</x-admin::layouts>
