<?php

namespace NextDeveloper\AI\Console\Commands;

use Illuminate\Console\Command;
use NextDeveloper\AI\Database\Models\LlmProviders;
use NextDeveloper\AI\Helpers\LlmKeyHelper;
use NextDeveloper\AI\Services\LlmProvidersService;

/**
 * Keeps the platform LiteLLM provider and its models (with current prices) in sync with the LiteLLM gateway.
 * The gateway url and key come from config('ai.llm.litellm'), never from the API.
 */
class SyncLlmModelsCommand extends Command
{
    protected $signature = 'ai:llm-sync';

    protected $description = 'Sync the platform LiteLLM provider and its models/prices into ai_llm_providers and ai_llm_models';

    public function handle(): int
    {
        $config = config('ai.llm.litellm');

        if (empty($config['url']) || empty($config['key'])) {
            $this->error('ai.llm.litellm.url / key are not configured (LITELLM_URL, LITELLM_KEY).');

            return self::FAILURE;
        }

        $slug = $config['slug'] ?? 'litellm';
        $values = [
            'name'              => 'PlusClouds AI Gateway',
            'driver'            => 'openai_compatible',
            'base_url'          => rtrim($config['url'], '/'),
            'api_key_encrypted' => LlmKeyHelper::encrypt($config['key']),
            'is_active'         => true,
        ];

        $provider = LlmProviders::withoutGlobalScopes()->whereNull('iam_account_id')->where('slug', $slug)->first();

        if ($provider) {
            $provider->forceFill($values)->saveQuietly();
        } else {
            $provider = new LlmProviders($values + ['slug' => $slug]);
            $provider->saveQuietly();
        }

        $counts = LlmProvidersService::syncProviderModels($provider->fresh());

        $this->info(sprintf('%s: %d created, %d updated, %d deactivated.', $slug, $counts['created'], $counts['updated'], $counts['deactivated']));

        return self::SUCCESS;
    }
}
