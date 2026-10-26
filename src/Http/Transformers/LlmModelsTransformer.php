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
            return $this->forViewer($transformed, $model);
        }

        $markup = 1 + ((float) config('ai.llm.markup_percent', 5)) / 100;
        $withMarkup = fn ($price) => $price === null ? null : round((float) $price * $markup, 6);

        // Prices with the markup an account is charged on platform models; the plain columns stay the upstream price.
        $transformed = array_merge(parent::transform($model), [
            'markup_percent'                     => (float) config('ai.llm.markup_percent', 5),
            'input_price_per_1m_with_markup'     => $withMarkup($model->input_price_per_1m),
            'output_price_per_1m_with_markup'    => $withMarkup($model->output_price_per_1m),
        ]);

        // Carbon objects do not survive the cache round trip (they come back as incomplete classes), so dates are cached as text.
        foreach (['created_at', 'updated_at', 'deleted_at'] as $column) {
            $transformed[$column] = $transformed[$column]?->toIso8601String();
        }

        Cache::set(
            CacheHelper::getKey('LlmModels', $model->uuid, 'Transformed'),
            $transformed
        );

        return $this->forViewer($transformed, $model);
    }

    /**
     * Everybody addresses a model by its public name, admins included.
     * This runs after the cache because the cached payload is shared between viewers.
     */
    private function forViewer(array $transformed, LlmModels $model): array
    {
        return self::maskUpstream($transformed, $model->public_id);
    }

    public static function maskUpstream(array $transformed, ?string $publicId): array
    {
        $transformed['public_id'] = $publicId;

        if (!empty($publicId)) {
            $transformed['model_id'] = $publicId;
        }

        return $transformed;
    }
}
