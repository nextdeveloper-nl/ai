<?php

namespace NextDeveloper\AI\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\AI\Database\Models\LlmBroadcastGroups;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\AI\Http\Transformers\AbstractTransformers\AbstractLlmBroadcastGroupsTransformer;

/**
 * Class LlmBroadcastGroupsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\AI\Http\Transformers
 */
class LlmBroadcastGroupsTransformer extends AbstractLlmBroadcastGroupsTransformer
{

    /**
     * @param LlmBroadcastGroups $model
     *
     * @return array
     */
    public function transform(LlmBroadcastGroups $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('LlmBroadcastGroups', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('LlmBroadcastGroups', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
