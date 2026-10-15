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

        $markup = 1 + ((float) config('ai.llm.markup_percent', 5)) / 100;
        $withMarkup = fn ($price) => $price === null ? null : round((float) $price * $markup, 6);

        // Prices with the markup an account is charged on platform models; the plain columns stay the upstream price.
        $transformed = array_merge(parent::transform($model), [
            'markup_percent'                     => (float) config('ai.llm.markup_percent', 5),
            'input_price_per_1m_with_markup'     => $withMarkup($model->input_price_per_1m),
            'output_price_per_1m_with_markup'    => $withMarkup($model->output_price_per_1m),
        ]);

        Cache::set(
            CacheHelper::getKey('LlmModels', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
