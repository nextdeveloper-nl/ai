<?php

namespace NextDeveloper\AI\Services\Llm;

use NextDeveloper\AI\Database\Models\LlmAliases;
use NextDeveloper\AI\Database\Models\LlmModels;
use NextDeveloper\AI\Database\Models\LlmProviders;
use NextDeveloper\AI\Services\Llm\Drivers\AnthropicDriver;
use NextDeveloper\AI\Services\Llm\Drivers\LlmDriver;
use NextDeveloper\AI\Services\Llm\Drivers\OpenAiCompatibleDriver;

/**
 * A fully resolved upstream target: which provider, which upstream model, and its price row (if any).
 */
class LlmTarget
{
    public function __construct(
        public readonly LlmProviders $provider,
        public readonly string $model,
        public readonly ?LlmModels $pricing,
        public readonly string $requestedModel,
        public readonly ?string $alias = null,
        public readonly ?LlmAliases $aliasRecord = null,
    ) {
    }

    /**
     * The same target for logging purposes, but reported as the answer to what the caller originally asked for
     * (used when a fallback target served an alias).
     */
    public function servingFor(LlmTarget $primary): self
    {
        return new self($this->provider, $this->model, $this->pricing, $primary->requestedModel, $primary->alias, $primary->aliasRecord);
    }

    /**
     * Account-owned providers use the account's own upstream key (BYOK), so we never charge for them.
     */
    public function isBilled(): bool
    {
        return $this->provider->iam_account_id === null;
    }

    public function driver(): LlmDriver
    {
        return match ($this->provider->driver) {
            'anthropic' => new AnthropicDriver(),
            default     => new OpenAiCompatibleDriver(),
        };
    }
}
