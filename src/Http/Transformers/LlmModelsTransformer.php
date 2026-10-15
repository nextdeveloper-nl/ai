<?php

namespace NextDeveloper\AI\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\AI\Database\Models\LlmModels;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\AI\Http\Transformers\AbstractTransformers\AbstractLlmModelsTransformer;

/**
 * Class LlmModelsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\AI\Http\Transformers
 */
class LlmModelsTransformer extends AbstractLlmModelsTransformer
{

    /**
     * @param LlmModels $model
     *
     * @return array
     */
    public function transform(LlmModels $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('LlmModels', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('LlmModels', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
