<?php

namespace NextDeveloper\AI\Http\Requests\LlmAliases;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class LlmAliasesCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
        'alias' => 'required|string',
        'target_provider' => 'required|string',
        'target_model' => 'required|string',
        'is_active' => 'boolean',
        'description' => 'nullable|string|max:500',
        'fallbacks' => 'nullable|array|max:5',
        'fallbacks.*.provider' => 'required_with:fallbacks|string',
        'fallbacks.*.model' => 'required_with:fallbacks|string',
        'fallback_on_error' => 'boolean',
        'fallback_on_rate_limit' => 'boolean',
        'fallback_on_timeout' => 'boolean',
        'fallback_timeout_seconds' => 'nullable|integer|min:1|max:600',
        'temperature' => 'nullable|numeric|min:0|max:2',
        'max_tokens' => 'nullable|integer|min:1',
        'system_prompt' => 'nullable|string|max:20000',
        'monthly_spend_limit' => 'nullable|numeric|min:0',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
