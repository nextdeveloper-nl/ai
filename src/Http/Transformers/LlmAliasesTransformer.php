<?php

namespace NextDeveloper\AI\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\AI\Database\Models\LlmAliases;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\AI\Http\Transformers\AbstractTransformers\AbstractLlmAliasesTransformer;

/**
 * Class LlmAliasesTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\AI\Http\Transformers
 */
class LlmAliasesTransformer extends AbstractLlmAliasesTransformer
{

    /**
     * @param LlmAliases $model
     *
     * @return array
     */
    public function transform(LlmAliases $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('LlmAliases', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = array_merge(parent::transform($model), [
            'description'              => $model->description,
            'fallbacks'                => $model->fallbacks ?? [],
            'fallback_on_error'        => (bool) $model->fallback_on_error,
            'fallback_on_rate_limit'   => (bool) $model->fallback_on_rate_limit,
            'fallback_on_timeout'      => (bool) $model->fallback_on_timeout,
            'fallback_timeout_seconds' => $model->fallback_timeout_seconds,
            'temperature'              => $model->temperature !== null ? (float) $model->temperature : null,
            'max_tokens'               => $model->max_tokens,
            'system_prompt'            => $model->system_prompt,
            'monthly_spend_limit'      => $model->monthly_spend_limit !== null ? (float) $model->monthly_spend_limit : null,
        ]);

        Cache::set(
            CacheHelper::getKey('LlmAliases', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
