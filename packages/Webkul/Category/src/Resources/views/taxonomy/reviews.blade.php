<x-admin::layouts>
    <x-slot:title>商品主类目审核</x-slot>

    <x-admin::layouts.page-header title="商品主类目审核" />

    <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">这里审核的是“商品属于哪个 PIM 标准类目”。包括完全无法分类、低置信度、多个候选冲突以及旧类目迁移后的商品。确认前可以修改标准类目 code；平台类目映射请在“平台类目映射”菜单处理。</p>

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-700">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="grid grid-cols-4 gap-3 mb-4 max-lg:grid-cols-2 max-sm:grid-cols-1">
        @foreach ([
            ['label' => '待审核', 'value' => $stats['proposed'], 'query' => ['status' => 'proposed']],
            ['label' => '其中：待分类', 'value' => $stats['uncategorized'], 'query' => ['status' => 'proposed', 'type' => 'uncategorized']],
            ['label' => '已确认', 'value' => $stats['confirmed'], 'query' => ['status' => 'confirmed']],
            ['label' => '已拒绝', 'value' => $stats['rejected'], 'query' => ['status' => 'rejected']],
        ] as $stat)
            <a class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow" href="{{ route('admin.catalog.taxonomy.reviews.index', $stat['query']) }}">
                <div class="text-sm text-gray-500">{{ $stat['label'] }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white">{{ number_format($stat['value']) }}</div>
            </a>
        @endforeach
    </div>

    <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
        <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
            <input class="h-10 min-w-[280px] flex-1 px-3 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="search" value="{{ request('search') }}" placeholder="搜索 SKU、标准类目路径或 code">
            <select class="h-10 min-w-[150px] px-3 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="status">
                <option value="">全部状态</option>
                @foreach (['proposed' => '待审核', 'confirmed' => '已确认', 'rejected' => '已拒绝'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status', 'proposed') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select class="h-10 min-w-[160px] px-3 border rounded dark:bg-cherry-800 dark:border-cherry-700" name="type">
                <option value="">全部分类结果</option>
                <option value="uncategorized" @selected(request('type') === 'uncategorized')>待分类</option>
                <option value="candidate" @selected(request('type') === 'candidate')>已有候选类目</option>
            </select>
            <button class="primary-button !h-10 !px-4 justify-center" type="submit">筛选</button>
            <a class="secondary-button !h-10 !px-4 justify-center" href="{{ route('admin.catalog.taxonomy.reviews.index') }}">重置</a>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b dark:border-cherry-700 text-left text-gray-500"><th class="py-2">SKU</th><th>当前建议类目</th><th>方法</th><th>置信度</th><th>状态</th><th>操作</th></tr></thead>
                <tbody>
                @forelse ($assignments as $assignment)
                    <tr class="border-b dark:border-cherry-800">
                        <td class="py-2.5"><a class="text-blue-600" href="{{ route('admin.catalog.products.edit', $assignment->product_id) }}">{{ $assignment->product->sku }}</a></td>
                        <td>{{ $assignment->category->source_path ?: $assignment->category->name }}<br><span class="font-mono text-xs text-gray-500">{{ $assignment->category->code }}</span></td>
                        <td>{{ $assignment->method }}</td>
                        <td>{{ $assignment->confidence === null ? '-' : number_format((float) $assignment->confidence * 100, 0).'%' }}</td>
                        <td>{{ ['proposed' => '待审核', 'confirmed' => '已确认', 'rejected' => '已拒绝'][$assignment->status] ?? $assignment->status }}</td>
                        <td>
                            @if ($assignment->status === 'proposed')
                                <button
                                    class="mr-3 text-blue-600"
                                    type="button"
                                    onclick="document.getElementById('assignment-dialog-{{ $assignment->id }}').showModal(); window.dispatchEvent(new CustomEvent('taxonomy-cascader:open', { detail: { componentIds: ['review-standard-{{ $assignment->id }}'] } }))"
                                >修改并确认</button>
                                <form method="POST" action="{{ route('admin.catalog.taxonomy.assignments.review', $assignment->id) }}" class="inline">
                                    @csrf @method('PUT')
                                    <button class="text-red-600" name="status" value="rejected" type="submit">拒绝</button>
                                </form>

                                <dialog id="assignment-dialog-{{ $assignment->id }}" class="w-[720px] max-w-[calc(100vw-2rem)] rounded bg-white p-0 text-left shadow-xl backdrop:bg-black/40 dark:bg-cherry-900 dark:text-white">
                                    <div class="flex items-center justify-between border-b p-4 dark:border-cherry-700">
                                        <div>
                                            <h2 class="text-base font-semibold">修改并确认商品主类目</h2>
                                            <p class="mt-1 text-xs text-gray-500">SKU：{{ $assignment->product->sku }}</p>
                                        </div>
                                        <form method="dialog"><button class="text-xl text-gray-500" type="submit" aria-label="关闭">×</button></form>
                                    </div>
                                    <form method="POST" action="{{ route('admin.catalog.taxonomy.assignments.review', $assignment->id) }}" class="grid gap-4 p-4">
                                        @csrf @method('PUT')
                                        <x-category::taxonomy-cascader
                                            component-id="review-standard-{{ $assignment->id }}"
                                            type="standard"
                                            input-name="category_code"
                                            :selected="$assignment->category->code === 'std_uncategorized' ? '' : $assignment->category->code"
                                            label="PIM 标准类目"
                                            lazy
                                            required
                                        />
                                        <div class="flex justify-end gap-2">
                                            <button class="secondary-button" type="button" onclick="document.getElementById('assignment-dialog-{{ $assignment->id }}').close()">取消</button>
                                            <button class="primary-button" name="status" value="confirmed" type="submit">确认类目</button>
                                        </div>
                                    </form>
                                </dialog>
                            @else
                                <span class="text-gray-400">已处理</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-gray-500">没有符合筛选条件的商品主类目记录</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-admin::pagination.full :paginator="$assignments" />
    </div>
</x-admin::layouts>
