<?php

namespace NextDeveloper\AI\Console\Commands;

use Illuminate\Console\Command;
use NextDeveloper\AI\Database\Models\LlmProviders;
use NextDeveloper\AI\Helpers\LlmKeyHelper;
use NextDeveloper\AI\Services\LlmProvidersService;

/**
 * Creates an upstream LLM provider (platform-wide, or owned by one account) and can pull its models right away.
 * The upstream key is read from a prompt or an environment variable, never from an argument, so it stays out of
 * the shell history.
 */
class CreateLlmProviderCommand extends Command
{
    private const DRIVERS = ['openai_compatible', 'anthropic'];

    protected $signature = 'ai:llm-provider-create
        {slug : Short identifier used in "provider/model" strings, e.g. greenference}
        {base_url : Upstream API base url, e.g. https://api.example.com/v1}
        {--name= : Human readable name (defaults to the slug)}
        {--driver=openai_compatible : openai_compatible or anthropic}
        {--key-env= : Name of the environment variable that holds the upstream key (asked for when omitted)}
        {--account= : iam_account_id that owns the provider (BYOK); omit for a platform provider}
        {--inactive : Create the provider switched off}
        {--sync : Pull the models of the provider right after creating it}';

    protected $description = 'Create an upstream LLM provider in ai_llm_providers, optionally syncing its models';

    public function handle(): int
    {
        $slug = (string) $this->argument('slug');
        $baseUrl = rtrim((string) $this->argument('base_url'), '/');
        $driver = (string) $this->option('driver');
        $accountId = $this->option('account') !== null ? (int) $this->option('account') : null;

        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $slug)) {
            $this->error('The slug may only contain lowercase letters, digits, ".", "_" and "-" (it is used before the "/" in "provider/model").');

            return self::FAILURE;
        }

        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $this->error("'{$baseUrl}' is not a valid url.");

            return self::FAILURE;
        }

        if (!in_array($driver, self::DRIVERS, true)) {
            $this->error('The driver must be one of: ' . implode(', ', self::DRIVERS) . '.');

            return self::FAILURE;
        }

        $exists = LlmProviders::withoutGlobalScopes()
            ->where('slug', $slug)
            ->when($accountId === null, fn ($q) => $q->whereNull('iam_account_id'), fn ($q) => $q->where('iam_account_id', $accountId))
            ->exists();

        if ($exists) {
            $this->error("A provider with the slug '{$slug}' already exists.");

            return self::FAILURE;
        }

        $key = $this->upstreamKey();

        if ($key === null) {
            return self::FAILURE;
        }

        $provider = new LlmProviders([
            'name'              => $this->option('name') ?: $slug,
            'slug'              => $slug,
            'driver'            => $driver,
            'base_url'          => $baseUrl,
            'api_key_encrypted' => $key === '' ? null : LlmKeyHelper::encrypt($key),
            'is_active'         => !$this->option('inactive'),
            'iam_account_id'    => $accountId,
        ]);
        $provider->saveQuietly();

        $this->info(sprintf('Provider %s created (%s, %s).', $slug, $accountId === null ? 'platform' : "account {$accountId}", $provider->is_active ? 'active' : 'inactive'));

        if ($this->option('sync')) {
            try {
                $counts = LlmProvidersService::syncProviderModels($provider->fresh());
            } catch (\Throwable $e) {
                $this->error('The provider was created but its models could not be synced: ' . $e->getMessage());

                return self::FAILURE;
            }

            $this->info(sprintf('Models: %d created, %d updated, %d deactivated.', $counts['created'], $counts['updated'], $counts['deactivated']));
        }

        return self::SUCCESS;
    }

    /**
     * @return string|null the key ('' for a provider without one), null when the key could not be read
     */
    private function upstreamKey(): ?string
    {
        if ($name = $this->option('key-env')) {
            $value = getenv($name);

            if ($value === false || $value === '') {
                $this->error("The environment variable {$name} is empty or not set.");

                return null;
            }

            return $value;
        }

        if (!$this->input->isInteractive()) {
            $this->error('Pass --key-env=NAME in a non-interactive run.');

            return null;
        }

        return (string) $this->secret('Upstream API key (leave empty for none)');
    }
}
