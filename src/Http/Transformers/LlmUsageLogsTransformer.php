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
            'attempts'           => $model->attempts ?? [],
        ]);

        Cache::set(
            CacheHelper::getKey('LlmUsageLogs', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
