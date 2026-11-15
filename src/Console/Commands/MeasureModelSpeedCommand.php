<?php

namespace NextDeveloper\AI\Console\Commands;

use Illuminate\Console\Command;
use NextDeveloper\AI\Database\Models\LlmModels;
use NextDeveloper\AI\Database\Models\LlmProviders;
use NextDeveloper\AI\Services\Llm\Drivers\AnthropicDriver;
use NextDeveloper\AI\Services\Llm\Drivers\OpenAiCompatibleDriver;
use NextDeveloper\AI\Services\Llm\ModelSpeed;
use NextDeveloper\AI\Services\PlatformAccount;
use NextDeveloper\AI\Helpers\LlmKeyHelper;

/**
 * Daily speed test: asks every active platform model for the same short answer, measures the output tokens per
 * second and stores the rating (capabilities.speed, 1 to 5) from the median of the last days (see ModelSpeed).
 * The calls go straight to the upstream: no customer is billed and no usage log is written.
 */
class MeasureModelSpeedCommand extends Command
{
    protected $signature = 'ai:llm-speed-test {model? : Only this model (public id or upstream model id)}';

    protected $description = 'Measure the speed of the platform LLM models and store their 1-5 speed rating';

    private const PROMPT = 'Count from 1 to 60 as plain numbers separated by single spaces. Write nothing else.';

    public function handle(): int
    {
        $today = now()->utc()->toDateString();
        $failed = false;

        $providers = LlmProviders::withoutGlobalScopes()->where('is_active', true)->get()
            ->filter(fn ($provider) => PlatformAccount::owns($provider->iam_account_id === null ? null : (int) $provider->iam_account_id));

        foreach ($providers as $provider) {
            $models = LlmModels::withoutGlobalScopes()
                ->where('ai_llm_provider_id', $provider->id)
                ->where('is_active', true)
                ->when($this->argument('model'), fn ($q, $model) => $q->where(fn ($q) => $q->where('public_id', $model)->orWhere('model_id', $model)))
                ->get();

            foreach ($models as $model) {
                $label = $model->public_id ?? $model->model_id;

                try {
                    $tokensPerSecond = $this->measure($provider, $model);
                } catch (\Throwable $e) {
                    $failed = true;
                    $this->error("{$label}: " . $e->getMessage());

                    continue;
                }

                if ($tokensPerSecond === null) {
                    $this->warn("{$label}: the answer was too short to measure.");

                    continue;
                }

                $capabilities = ModelSpeed::record((array) $model->capabilities, $tokensPerSecond, $today);

                LlmModels::withoutGlobalScopes()->whereKey($model->id)->update(['capabilities' => json_encode($capabilities)]);

                $this->info(sprintf('%s: %.1f tokens/s now, median %.1f over %d day(s), speed %d.', $label, $tokensPerSecond, $capabilities['speed_tps'], count($capabilities['speed_samples']), $capabilities['speed']));
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function measure(LlmProviders $provider, LlmModels $model): ?float
    {
        $driver = $provider->driver === 'anthropic' ? new AnthropicDriver() : new OpenAiCompatibleDriver();
        $key = $provider->api_key_encrypted ? LlmKeyHelper::decrypt($provider->api_key_encrypted) : '';
        $payload = [
            'model'      => $model->model_id,
            'messages'   => [['role' => 'user', 'content' => self::PROMPT]],
            'max_tokens' => 400,
            'temperature' => 0,
        ];
        $timeout = (int) config('ai.llm.timeout', 120);
        $startedAt = microtime(true);

        if (($model->capabilities['streaming'] ?? true) !== false) {
            $result = $driver->stream($payload, $provider->base_url, $key, $timeout, fn (string $chunk) => null);
            $firstTokenMs = $result['ttft_ms'] ?? null;
        } else {
            $result = $driver->chat($payload, $provider->base_url, $key, $timeout);
            $firstTokenMs = null;
        }

        if (($result['status'] ?? 500) >= 400) {
            throw new \RuntimeException('the upstream answered with status ' . $result['status']);
        }

        return ModelSpeed::tokensPerSecond((int) ($result['output_tokens'] ?? 0), (int) round((microtime(true) - $startedAt) * 1000), $firstTokenMs);
    }
}
