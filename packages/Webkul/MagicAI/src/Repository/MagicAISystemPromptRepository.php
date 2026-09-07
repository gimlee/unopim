<?php

namespace Webkul\MagicAI\Repository;

use Webkul\Core\Eloquent\Repository;
use Webkul\MagicAI\Contracts\MagicAISystemPrompt;

class MagicAISystemPromptRepository extends Repository
{
    /**
     * Specify the Model class name
     */
    public function model(): string
    {
        return MagicAISystemPrompt::class;
    }

    /**
     * Get all tone options for dropdown
     */
    public function getAllPromptOptions(): array
    {
        return $this->model->newQuery()
            ->where('purpose', 'general')
            ->get()
            ->map(fn ($prompt): array => [
                'id'         => $prompt->id,
                'label'      => ucfirst((string) $prompt->title),
                'is_enabled' => (bool) $prompt->is_enabled,
            ])->toArray();
    }

    /**
     * Disable all enabled system prompts
     */
    public function disableAllEnabledPrompts(string $purpose = 'general'): int
    {
        return $this->model
            ->where('purpose', $purpose)
            ->where('is_enabled', true)
            ->update(['is_enabled' => false]);
    }
}
