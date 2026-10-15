<?php

namespace NextDeveloper\AI\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\AI\Database\Models\LlmProviders;
use NextDeveloper\AI\Services\LlmProvidersService;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\AI\Http\Transformers\AbstractTransformers\AbstractLlmProvidersTransformer;

/**
 * Class LlmProvidersTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\AI\Http\Transformers
 */
class LlmProvidersTransformer extends AbstractLlmProvidersTransformer
{

    /**
     * @param LlmProviders $model
     *
     * @return array
     */
    public function transform(LlmProviders $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('LlmProviders', $model->uuid, 'Transformed')
        );

        if(!$transformed) {
            $transformed = parent::transform($model);

            Cache::set(
                CacheHelper::getKey('LlmProviders', $model->uuid, 'Transformed'),
                $transformed
            );
        }

        // Model counts and health change all the time, so they are added after the cache.
        return array_merge($transformed, LlmProvidersService::healthSummary($model));
    }
}
