<?php

namespace NextDeveloper\AI\Console\Commands;

use Illuminate\Console\Command;
use NextDeveloper\AI\Database\Models\LlmAliases;
use NextDeveloper\AI\Services\Llm\ModelResolver;

/**
 * Creates the platform aliases (iam_account_id NULL, visible to every account) the AI helpers send as their model,
 * so which public model a helper runs on is data an admin can change, not code.
 */
class SeedHelperAliasesCommand extends Command
{
    /**
     * The model names the helpers of the assistance service send.
     *
     * @var array<int, string>
     */
    public const HELPER_ALIASES = [
        'blog-general',
        'company-analyzer',
        'crm-quick-add',
        'generic',
        'iam-general',
        'intelligence-custom',
        'intelligence-general',
        'intelligences-general',
        'leadocean-general',
        'lo-sales-outreach',
        'partnership-general',
        'support-general',
        'translation',
    ];

    protected $signature = 'ai:llm-seed-helper-aliases
        {--target=qwen3.6-27b : Public model (without the prefix) the aliases point at}
        {--force : Repoint aliases that already exist}
        {--dry-run : Only show what would happen}';

    protected $description = 'Create the platform aliases of the AI helper models, pointing at a public model';

    public function handle(): int
    {
        $prefix = ModelResolver::publicPrefix();
        $target = ltrim((string) $this->option('target'), '/');
        $target = str_starts_with($target, $prefix . '/') ? substr($target, strlen($prefix) + 1) : $target;

        foreach (self::HELPER_ALIASES as $name) {
            $alias = LlmAliases::withoutGlobalScopes()->whereNull('iam_account_id')->where('alias', $name)->first();

            if ($alias && !$this->option('force')) {
                $this->line("{$name}: exists ({$alias->target_provider}/{$alias->target_model}), left alone.");

                continue;
            }

            $this->info(sprintf('%s: %s %s/%s', $name, $alias ? 'repointed to' : 'created for', $prefix, $target));

            if ($this->option('dry-run')) {
                continue;
            }

            $values = ['target_provider' => $prefix, 'target_model' => $target, 'is_active' => true];

            if ($alias) {
                $alias->forceFill($values)->saveQuietly();
            } else {
                (new LlmAliases($values + ['alias' => $name]))->saveQuietly();
            }
        }

        return self::SUCCESS;
    }
}
