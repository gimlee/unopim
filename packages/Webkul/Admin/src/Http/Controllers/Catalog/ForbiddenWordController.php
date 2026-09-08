<?php

namespace Webkul\Admin\Http\Controllers\Catalog;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Catalog\ForbiddenWordDataGrid;
use Webkul\Admin\Http\Controllers\Controller;

class ForbiddenWordController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! bouncer()->hasPermission('catalog.forbidden_words')) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function index(): View|JsonResponse
    {
        if (request()->ajax() || request()->wantsJson()) {
            return app(ForbiddenWordDataGrid::class)->toJson();
        }

        return view('admin::catalog.forbidden-words.index');
    }

    public function show(int $id): JsonResponse
    {
        $word = DB::table('content_policy_forbidden_words')->find($id);
        abort_if(! $word, 404);

        return response()->json(['data' => $word]);
    }

    public function store(): JsonResponse
    {
        $data = $this->validated();
        $normalized = $this->normalized($data['term']);
        $this->ensureUnique($normalized);

        $id = DB::table('content_policy_forbidden_words')->insertGetId([
            'term'            => trim($data['term']),
            'normalized_term' => $normalized,
            'status'          => (bool) $data['status'],
            'notes'           => $data['notes'] ?? null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json(['message' => '违禁词已创建。', 'data' => ['id' => $id]]);
    }

    public function update(int $id): JsonResponse
    {
        abort_if(! DB::table('content_policy_forbidden_words')->where('id', $id)->exists(), 404);
        $data = $this->validated();
        $normalized = $this->normalized($data['term']);
        $this->ensureUnique($normalized, $id);

        DB::table('content_policy_forbidden_words')->where('id', $id)->update([
            'term'            => trim($data['term']),
            'normalized_term' => $normalized,
            'status'          => (bool) $data['status'],
            'notes'           => $data['notes'] ?? null,
            'updated_at'      => now(),
        ]);

        return response()->json(['message' => '违禁词已更新。']);
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = DB::table('content_policy_forbidden_words')->where('id', $id)->delete();
        abort_if(! $deleted, 404);

        return response()->json(['message' => '违禁词已删除。']);
    }

    /** @return array{term: string, status: bool, notes?: string|null} */
    protected function validated(): array
    {
        return request()->validate([
            'term'   => ['required', 'string', 'max:255'],
            'status' => ['required', 'boolean'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);
    }

    protected function normalized(string $term): string
    {
        return mb_strtolower(trim($term));
    }

    protected function ensureUnique(string $normalized, ?int $exceptId = null): void
    {
        $query = DB::table('content_policy_forbidden_words')->where('normalized_term', $normalized);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['term' => '该违禁词已经存在。']);
        }
    }
}
