<?php

namespace NextDeveloper\AI\Console\Commands;

use Illuminate\Console\Command;
use NextDeveloper\AI\Database\Models\LlmProviders;
use NextDeveloper\AI\Services\LlmProvidersService;

/**
 * Keeps the models and prices of the platform providers in sync with what the providers list themselves.
 * A provider's url and key live in ai_llm_providers (see ai:llm-provider-create), never in configuration.
 */
class SyncLlmModelsCommand extends Command
{
    protected $signature = 'ai:llm-sync {slug? : Only sync this provider (default: every active platform provider)}';

    protected $description = 'Sync the models and prices of the platform LLM providers into ai_llm_models';

    public function handle(): int
    {
        $providers = LlmProviders::withoutGlobalScopes()
            ->whereNull('iam_account_id')
            ->where('is_active', true)
            ->when($this->argument('slug'), fn ($q, $slug) => $q->where('slug', $slug))
            ->orderBy('slug')
            ->get();

        if ($providers->isEmpty()) {
            $this->error('No active platform provider found. Create one with ai:llm-provider-create.');

            return self::FAILURE;
        }

        $failed = false;

        foreach ($providers as $provider) {
            try {
                $counts = LlmProvidersService::syncProviderModels($provider);
            } catch (\Throwable $e) {
                $failed = true;
                $this->error("{$provider->slug}: " . $e->getMessage());

                continue;
            }

            $this->info(sprintf('%s: %d created, %d updated, %d deactivated.', $provider->slug, $counts['created'], $counts['updated'], $counts['deactivated']));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
