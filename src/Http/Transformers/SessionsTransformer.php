<?php

namespace NextDeveloper\AI\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\AI\Database\Models\Sessions;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\AI\Http\Transformers\AbstractTransformers\AbstractSessionsTransformer;

/**
 * Class SessionsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\AI\Http\Transformers
 */
class SessionsTransformer extends AbstractSessionsTransformer
{

    /**
     * @param Sessions $model
     *
     * @return array
     */
    public function transform(Sessions $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('Sessions', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('Sessions', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
