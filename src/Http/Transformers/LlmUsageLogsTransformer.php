<?php

namespace NextDeveloper\AI\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\AI\Database\Models\LlmUsageLogs;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\AI\Http\Transformers\AbstractTransformers\AbstractLlmUsageLogsTransformer;

/**
 * Class LlmUsageLogsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\AI\Http\Transformers
 */
class LlmUsageLogsTransformer extends AbstractLlmUsageLogsTransformer
{

    /**
     * @param LlmUsageLogs $model
     *
     * @return array
     */
    public function transform(LlmUsageLogs $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('LlmUsageLogs', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = array_merge(parent::transform($model), [
            'alias'              => $model->alias ?? null,
            'served_by_fallback' => (bool) $model->served_by_fallback,
            // Customers see the model they asked for, never the provider behind it or what it said.
            'provider'           => null,
            'ai_llm_provider_id' => null,
            'upstream_cost'      => null,
            'model'              => $model->requested_model ?? $model->model,
            'error_message'      => $model->error_message ? 'The model could not complete the request.' : null,
            'attempts'           => array_map(
                fn ($attempt) => array_intersect_key((array) $attempt, array_flip(['public_model', 'status', 'status_code', 'latency_ms'])),
                (array) ($model->attempts ?? [])
            ),
        ]);

        // Carbon objects do not survive the cache round trip (they come back without a timezone marker),
        // so the dates are cached as ISO-8601 UTC strings.
        foreach (['created_at', 'updated_at', 'deleted_at'] as $dateField) {
            if (($transformed[$dateField] ?? null) instanceof \DateTimeInterface) {
                $transformed[$dateField] = \Illuminate\Support\Carbon::instance($transformed[$dateField])->utc()->toIso8601ZuluString('microsecond');
            }
        }

        Cache::set(
            CacheHelper::getKey('LlmUsageLogs', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
