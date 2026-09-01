<?php

namespace Webkul\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Webkul\Core\Rules\Code;

class CategoryRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $uniqueRule = 'unique:categories,code';

        if (! empty($this->id)) {
            $uniqueRule .= ','.$this->id;
        }

        $parentRule = ['nullable', 'integer', 'exists:categories,id'];

        $taxonomyRules = [
            'taxonomy_type' => ['sometimes', 'required', 'in:standard,container,source,platform,legacy'],
            'taxonomy_status' => ['sometimes', 'required', 'in:active,inactive,deprecated'],
            'is_assignable' => ['sometimes', 'boolean'],
            'source_platform' => ['nullable', 'string', 'max:64'],
            'source_external_id' => ['nullable', 'string', 'max:255'],
            'source_path' => ['nullable', 'string'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'sync_locked' => ['sometimes', 'boolean'],
            'taxonomy_aliases' => ['nullable', 'string'],
            'taxonomy_rules' => ['nullable', 'json'],
        ];

        if ($this->id) {
            return $taxonomyRules + [
                'code' => [
                    $uniqueRule,
                    new Code,
                ],
                'parent_id' => $parentRule,
            ];
        }

        return $taxonomyRules + [
            'code' => [
                'required',
                $uniqueRule,
                new Code,
            ],
            'parent_id' => $parentRule,
        ];
    }
}
