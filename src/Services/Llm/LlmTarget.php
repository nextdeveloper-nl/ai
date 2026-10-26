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
 * For a public model id, $alternates holds the other providers' offers of the same model, cheapest first.
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
        public readonly ?string $publicId = null,
        public readonly array $alternates = [],
    ) {
    }

    /**
     * The same target for logging purposes, but reported as the answer to what the caller originally asked for
     * (used when a fallback target served an alias).
     */
    public function servingFor(LlmTarget $primary): self
    {
        return new self($this->provider, $this->model, $this->pricing, $primary->requestedModel, $primary->alias, $primary->aliasRecord, $primary->publicId);
    }

    /**
     * The same target with the other offers of its public model to fall back on.
     *
     * @param array<int, LlmTarget> $alternates
     */
    public function withAlternates(array $alternates): self
    {
        return new self($this->provider, $this->model, $this->pricing, $this->requestedModel, $this->alias, $this->aliasRecord, $this->publicId, $alternates);
    }

    /**
     * Account-owned providers use the account's own upstream key (BYOK), so we never charge for them, except for
     * their public models: everybody else pays for those.
     */
    public function isBilled(): bool
    {
        return $this->provider->iam_account_id === null || (bool) $this->pricing?->is_public;
    }

    public function driver(): LlmDriver
    {
        return match ($this->provider->driver) {
            'anthropic' => new AnthropicDriver(),
            default     => new OpenAiCompatibleDriver(),
        };
    }
}
