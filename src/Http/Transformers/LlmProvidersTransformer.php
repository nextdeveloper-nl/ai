<?php

namespace NextDeveloper\AI\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\AI\Database\Models\LlmProviders;
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

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('LlmProviders', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
