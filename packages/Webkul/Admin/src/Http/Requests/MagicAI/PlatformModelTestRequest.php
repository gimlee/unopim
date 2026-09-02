<?php

namespace Webkul\Admin\Http\Requests\MagicAI;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Webkul\MagicAI\Enums\AiProvider;

class PlatformModelTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return bouncer()->hasPermission('ai-agent.platform');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id'       => ['nullable', 'integer', 'exists:magic_ai_platforms,id'],
            'provider' => ['required', Rule::enum(AiProvider::class)],
            'api_key'  => ['nullable', 'string'],
            'api_url'  => ['nullable', 'url', 'max:500'],
            'model'    => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-._:\/@]+$/'],
            'extras'   => ['nullable', 'json'],
        ];
    }
}
