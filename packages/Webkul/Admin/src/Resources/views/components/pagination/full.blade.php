@props([
    'paginator',
    'perPageOptions' => [20, 50, 100, 200],
])

@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $from = $paginator->firstItem() ?? 0;
    $to = $paginator->lastItem() ?? 0;
    $query = request()->except(['page', 'per_page']);
    $windowStart = max(1, $current - 2);
    $windowEnd = min($last, $current + 2);
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4 text-sm dark:border-cherry-800']) }}>
    <div class="text-gray-500 dark:text-gray-300">
        显示 {{ number_format($from) }}–{{ number_format($to) }}，共 {{ number_format($paginator->total()) }} 条
    </div>

    <div class="flex flex-wrap items-center justify-end gap-2">
        <form method="GET" class="flex items-center gap-2">
            @foreach ($query as $name => $value)
                @if (is_scalar($value))
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endif
            @endforeach
            <label class="whitespace-nowrap text-gray-500 dark:text-gray-300" for="pagination-per-page-{{ $paginator->getPageName() }}">每页</label>
            <select
                id="pagination-per-page-{{ $paginator->getPageName() }}"
                name="per_page"
                class="h-9 rounded border border-gray-200 bg-white px-2 dark:border-cherry-700 dark:bg-cherry-800"
                onchange="this.form.submit()"
            >
                @foreach ($perPageOptions as $option)
                    <option value="{{ $option }}" @selected($paginator->perPage() === (int) $option)>{{ $option }}</option>
                @endforeach
            </select>
        </form>

        <nav class="inline-flex items-center gap-1" aria-label="分页">
            <a class="secondary-button !h-9 !px-2.5 {{ $current <= 1 ? 'pointer-events-none opacity-50' : '' }}" href="{{ $paginator->url(1) }}" aria-label="首页">«</a>
            <a class="secondary-button !h-9 !px-2.5 {{ $current <= 1 ? 'pointer-events-none opacity-50' : '' }}" href="{{ $paginator->previousPageUrl() ?: $paginator->url(1) }}" aria-label="上一页">‹</a>

            @for ($page = $windowStart; $page <= $windowEnd; $page++)
                <a
                    class="{{ $page === $current ? 'primary-button' : 'secondary-button' }} !h-9 !min-w-9 !px-2.5"
                    href="{{ $paginator->url($page) }}"
                    aria-current="{{ $page === $current ? 'page' : 'false' }}"
                >{{ $page }}</a>
            @endfor

            <a class="secondary-button !h-9 !px-2.5 {{ $current >= $last ? 'pointer-events-none opacity-50' : '' }}" href="{{ $paginator->nextPageUrl() ?: $paginator->url($last) }}" aria-label="下一页">›</a>
            <a class="secondary-button !h-9 !px-2.5 {{ $current >= $last ? 'pointer-events-none opacity-50' : '' }}" href="{{ $paginator->url($last) }}" aria-label="末页">»</a>
        </nav>

        <form method="GET" class="flex items-center gap-2">
            @foreach ($query as $name => $value)
                @if (is_scalar($value))
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endif
            @endforeach
            <input type="hidden" name="per_page" value="{{ $paginator->perPage() }}">
            <label class="whitespace-nowrap text-gray-500 dark:text-gray-300" for="pagination-page-{{ $paginator->getPageName() }}">跳至</label>
            <input
                id="pagination-page-{{ $paginator->getPageName() }}"
                name="page"
                type="number"
                min="1"
                max="{{ max(1, $last) }}"
                value="{{ $current }}"
                class="h-9 w-20 rounded border border-gray-200 bg-white px-2 text-center dark:border-cherry-700 dark:bg-cherry-800"
            >
            <button class="secondary-button !h-9 !px-3" type="submit">跳转</button>
        </form>
    </div>
</div>
