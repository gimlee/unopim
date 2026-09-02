<x-admin::layouts>
    <x-slot:title>新增 / 更新平台映射</x-slot>

    <x-admin::layouts.page-header title="新增 / 更新平台映射" />

    <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">填写相同的 PIM 标准类目 code 和平台类目 ID 时会更新已有映射；填写新的组合时会新增映射。修改待审核或已拒绝记录，也可以直接在销售平台映射列表点击“编辑”。</p>

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-700">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="grid grid-cols-[minmax(0,640px)_minmax(280px,1fr)] gap-4 max-lg:grid-cols-1">
        <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
            <form method="POST" action="{{ route('admin.catalog.taxonomy.mappings.store') }}" class="flex flex-col gap-3">
                @csrf
                @include('category::taxonomy._mapping-fields', ['mapping' => null])
                <button class="primary-button justify-center" type="submit">保存映射</button>
            </form>
        </div>

        <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow self-start">
            <h2 class="mb-3 text-base font-semibold text-gray-800 dark:text-white">已同步平台类目版本</h2>
            @forelse ($taxonomies as $taxonomy)
                <div class="flex justify-between py-2 border-b dark:border-cherry-800 text-sm">
                    <span>{{ strtoupper($taxonomy->platform) }} / {{ $taxonomy->region ?: 'GLOBAL' }}<br><span class="text-xs text-gray-500">{{ $taxonomy->version }}</span></span>
                    <span class="text-right">{{ $taxonomy->categories_count }} 类目<br><span class="text-xs text-gray-500">{{ $taxonomy->status }}</span></span>
                </div>
            @empty
                <p class="text-sm text-gray-500">尚未同步平台类目</p>
            @endforelse
        </div>
    </div>
</x-admin::layouts>
