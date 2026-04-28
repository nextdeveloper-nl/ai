<?php

namespace NextDeveloper\AI\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\AI\Database\Models\Agents;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\AI\Http\Transformers\AbstractTransformers\AbstractAgentsTransformer;

/**
 * Class AgentsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\AI\Http\Transformers
 */
class AgentsTransformer extends AbstractAgentsTransformer
{

    /**
     * @param Agents $model
     *
     * @return array
     */
    public function transform(Agents $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('Agents', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('Agents', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
