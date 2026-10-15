<?php

namespace NextDeveloper\AI\Http\Transformers;

use NextDeveloper\AI\Database\Models\LlmApiKeys;
use NextDeveloper\AI\Http\Transformers\AbstractTransformers\AbstractLlmApiKeysTransformer;

/**
 * Class LlmApiKeysTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\AI\Http\Transformers
 */
class LlmApiKeysTransformer extends AbstractLlmApiKeysTransformer
{

    /**
     * @param LlmApiKeys $model
     *
     * @return array
     */
    public function transform(LlmApiKeys $model)
    {
        // Intentionally not cached: the response of create/regenerate carries the one-time plaintext key,
        // and it must never end up in the transformed-object cache.
        $transformed = parent::transform($model);

        if (!empty($model->plain_key)) {
            $transformed['key'] = $model->plain_key;
        }

        return $transformed;
    }
}
