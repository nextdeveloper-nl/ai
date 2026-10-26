<?php

namespace NextDeveloper\AI\Services;

use NextDeveloper\AI\Database\Models\LlmModels;
use NextDeveloper\AI\Database\Models\LlmProviders;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\AI\Services\AbstractServices\AbstractLlmModelsService;

/**
 * This class is responsible from managing the data for LlmModels
 *
 * Class LlmModelsService.
 *
 * @package NextDeveloper\AI\Database\Models
 */
class LlmModelsService extends AbstractLlmModelsService
{
    /**
     * Creates a model under a provider. The model inherits the provider's owner: a model of a platform
     * provider is a platform model (no account), and only ai-admin may add one.
     */
    public static function create(array $data)
    {
        $provider = LlmProviders::where('uuid', $data['ai_llm_provider_id'] ?? null)->first();

        throw_if(!$provider, new NotAllowedException('Provider not found or not allowed.'));

        if ($provider->iam_account_id === null) {
            throw_if(
                !UserHelper::hasRole('ai-admin'),
                new NotAllowedException('Only ai-admin can add models to platform providers.')
            );

            $data['ai_llm_provider_id'] = $provider->id;
            $data['iam_user_id'] = UserHelper::me()->id;
            $data['iam_account_id'] = null;

            return LlmModels::create($data)->fresh();
        }

        unset($data['is_public']);

        // The abstract create() would try to turn an id into a uuid lookup; give it the uuid it expects.
        $data['iam_account_id'] = \NextDeveloper\IAM\Database\Models\Accounts::withoutGlobalScopes()->find($provider->iam_account_id)->uuid;

        return parent::create($data);
    }

    /**
     * Only ai-admin decides which models are public: a public model is served to every account, so the owner of an
     * account provider must not be able to publish one. The owner still edits the rest of the model freely.
     */
    public static function update($id, array $data)
    {
        if (!UserHelper::hasRole('ai-admin')) {
            unset($data['is_public']);
        }

        return parent::update($id, $data);
    }

    /**
     * Upstream cost in USD for the given token counts, or null when the model has no prices.
     */
    public static function calculateCost(LlmModels $model, int $inputTokens, int $outputTokens, int $cacheReadTokens = 0, int $cacheWriteTokens = 0): ?float
    {
        if ($model->input_price_per_1m === null || $model->output_price_per_1m === null) {
            return null;
        }

        $input = (float) $model->input_price_per_1m;

        // Cached tokens are part of the input count; they cost their own price, or the normal input price when none is set.
        $cacheRead = min($cacheReadTokens, $inputTokens);
        $cacheWrite = min($cacheWriteTokens, $inputTokens - $cacheRead);
        $plain = $inputTokens - $cacheRead - $cacheWrite;

        return round(
            ($plain / 1_000_000) * $input
            + ($cacheRead / 1_000_000) * (float) ($model->cache_read_price_per_1m ?? $input)
            + ($cacheWrite / 1_000_000) * (float) ($model->cache_write_price_per_1m ?? $input)
            + ($outputTokens / 1_000_000) * (float) $model->output_price_per_1m,
            8
        );
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
