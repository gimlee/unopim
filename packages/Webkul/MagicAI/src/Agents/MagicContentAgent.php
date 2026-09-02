<?php

namespace Webkul\MagicAI\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Dynamic AI agent for Magic AI content generation.
 *
 * Temperature and max tokens are resolved natively by laravel/ai via the
 * optional agent option methods; a null temperature omits the parameter,
 * which reasoning models require.
 */
class MagicContentAgent implements Agent, HasProviderOptions
{
    use Promptable;

    public function __construct(
        protected string $systemPrompt = '',
        protected ?float $temperature = 0.7,
        protected ?int $maxTokens = 1054,
        protected array $providerOptions = [],
    ) {}

    public function instructions(): string
    {
        return $this->systemPrompt;
    }

    public function temperature(): ?float
    {
        return $this->temperature;
    }

    public function maxTokens(): ?int
    {
        return $this->maxTokens;
    }

    /**
     * Pass provider-specific request body fields to laravel/ai.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return $this->providerOptions;
    }
}
