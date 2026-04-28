<?php

namespace NextDeveloper\AI\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\AI\Database\Models\AvailableHelpers;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\AI\Http\Transformers\AbstractTransformers\AbstractAvailableHelpersTransformer;

/**
 * Class AvailableHelpersTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\AI\Http\Transformers
 */
class AvailableHelpersTransformer extends AbstractAvailableHelpersTransformer
{

    /**
     * @param AvailableHelpers $model
     *
     * @return array
     */
    public function transform(AvailableHelpers $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('AvailableHelpers', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('AvailableHelpers', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
