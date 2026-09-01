<?php

namespace Webkul\AdminApi\Http\Requests\Catalog;

use Webkul\AdminApi\Http\Requests\ApiFormRequest;

class PartialUpdateCategoryRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'additional_data' => ['sometimes', 'array'],
            'parent'          => ['sometimes', 'nullable', 'string'],
            'taxonomy_type'   => ['sometimes', 'in:standard,container,source,platform,legacy'],
            'is_assignable'   => ['sometimes', 'boolean'],
            'status'          => ['sometimes', 'in:active,inactive,deprecated'],
            'source_platform' => ['sometimes', 'nullable', 'string', 'max:64'],
            'source_external_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_path'     => ['sometimes', 'nullable', 'string'],
            'source_url'      => ['sometimes', 'nullable', 'url', 'max:2048'],
            'sync_locked'     => ['sometimes', 'boolean'],
        ];
    }
}
