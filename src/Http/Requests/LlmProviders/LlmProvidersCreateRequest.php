<?php

namespace NextDeveloper\AI\Http\Requests\LlmProviders;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class LlmProvidersCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
        'name' => 'required|string',
        'slug' => 'required|string',
        'driver' => 'required|string|in:openai_compatible,anthropic',
        'base_url' => 'required|string',
        'is_active' => 'boolean',
        'config' => 'nullable|array',
        'api_key' => 'nullable|string',
        'is_platform' => 'boolean',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
