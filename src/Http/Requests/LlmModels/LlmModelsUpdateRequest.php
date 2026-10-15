<?php

namespace NextDeveloper\AI\Http\Requests\LlmModels;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class LlmModelsUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
        'ai_llm_provider_id' => 'nullable|exists:ai_llm_providers,uuid|uuid',
        'model_id' => 'nullable|string',
        'display_name' => 'nullable|string',
        'input_price_per_1m' => 'nullable|numeric|min:0',
        'output_price_per_1m' => 'nullable|numeric|min:0',
        'context_window' => 'nullable|integer',
        'capabilities' => 'nullable|array',
        'is_active' => 'boolean',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
