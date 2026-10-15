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

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('LlmAliases', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
