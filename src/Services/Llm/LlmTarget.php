<?php

namespace NextDeveloper\AI\Services\Llm;

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
    ) {
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
