<?php

namespace Webkul\AdminApi\Http\Controllers\API\MagicAI;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\AdminApi\Http\Controllers\API\ApiController;
use Webkul\MagicAI\Models\MagicAISystemPrompt;

class MagicAISystemPromptSyncController extends ApiController
{
    public function index(): JsonResponse
    {
        $prompts = MagicAISystemPrompt::query()
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $prompts]);
    }

    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prompts'               => ['required', 'array'],
            'prompts.*.title'       => ['required', 'string', 'max:255'],
            'prompts.*.purpose'     => ['required', 'string', 'max:64'],
            'prompts.*.tone'        => ['required', 'string'],
            'prompts.*.max_tokens'  => ['sometimes', 'nullable', 'integer', 'min:1'],
            'prompts.*.temperature' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:2'],
            'prompts.*.is_enabled'  => ['sometimes', 'nullable', 'boolean'],
        ]);

        $created = [];
        $updated = [];
        $unchanged = [];

        foreach ($data['prompts'] as $promptData) {
            $purpose = trim((string) $promptData['purpose']);
            $title = trim((string) $promptData['title']);
            $key = "{$purpose}:{$title}";

            $desired = [
                'title'       => $title,
                'purpose'     => $purpose,
                'tone'        => (string) $promptData['tone'],
                'max_tokens'  => isset($promptData['max_tokens']) ? (int) $promptData['max_tokens'] : 1024,
                'temperature' => isset($promptData['temperature']) ? (float) $promptData['temperature'] : 0.7,
                'is_enabled'  => isset($promptData['is_enabled']) ? (bool) $promptData['is_enabled'] : true,
            ];

            $existing = MagicAISystemPrompt::query()
                ->where('purpose', $purpose)
                ->where('title', $title)
                ->first();

            if (! $existing) {
                MagicAISystemPrompt::create($desired);
                $created[] = $key;
                continue;
            }

            $hasChanged = (string) $existing->tone !== (string) $desired['tone']
                || (int) $existing->max_tokens !== (int) $desired['max_tokens']
                || abs((float) $existing->temperature - (float) $desired['temperature']) > 0.001
                || (bool) $existing->is_enabled !== (bool) $desired['is_enabled'];

            if ($hasChanged) {
                $existing->update($desired);
                $updated[] = $key;
            } else {
                $unchanged[] = $key;
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'created'   => $created,
                'updated'   => $updated,
                'unchanged' => $unchanged,
                'total'     => count($data['prompts']),
            ],
        ]);
    }
}
